<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Auto-kolabs (Daniel 2026-10-02): "kolabing should be able to set auto kolabs,
 * we automatch them and the kolab appears active".
 *
 * A maintainer picks a business and a community; this creates a Kolab that is
 * already matched, exactly as if the community had applied and the business had
 * accepted. Sales can then promise "your first Kolab" and deliver it.
 *
 * It walks the real path rather than writing rows by hand, so everything that
 * hangs off a real match behaves the same:
 *   1. KolabService::create() writes the business's offer (venue enrichment,
 *      city canonicalisation), composed like its auto listing.
 *   2. The offer is published as a direct proposal to that one community
 *      (`recipient_community_id`), so it never shows in anyone else's Explore.
 *   3. ApplicationService::apply() files the community's application.
 *   4. ApplicationService::accept() accepts it: collaboration, happening,
 *      missions, analytics, reminders.
 *   5. The chat thread is opened so either side can say hello.
 * Steps 3 and 4 run with their own notifications off; both owners instead get
 * one "Kolabing set up a Kolab for you" notification (in-app + push + the
 * existing collab-confirmed email, each behind the recipient's own switches).
 *
 * The Kolab is marked `is_auto_matched` with the maintainer's id for analytics.
 * It does not consume the business's free Kolab (Profile::hasUsedFreeKolab).
 */
class AutoKolabService
{
    public function __construct(
        private readonly KolabService $kolabService,
        private readonly ApplicationService $applicationService,
        private readonly BusinessAutoListingService $autoListing,
        private readonly NotificationService $notificationService,
        private readonly NotificationReminderService $notificationReminderService,
        private readonly ChatService $chatService,
    ) {}

    /**
     * Find a business by profile id, email or exact business name.
     *
     * @throws InvalidArgumentException when nothing (or more than one) matches
     */
    public function resolveBusiness(string $reference): Profile
    {
        return $this->resolveProfile($reference, UserType::Business);
    }

    /**
     * Find a community by profile id, email or exact community name.
     *
     * @throws InvalidArgumentException when nothing (or more than one) matches
     */
    public function resolveCommunity(string $reference): Profile
    {
        return $this->resolveProfile($reference, UserType::Community);
    }

    /**
     * What create() would do, without writing anything. `problems` block the
     * create; `warnings` do not.
     *
     * @param  array{date?: string|null, title?: string|null}  $options
     * @return array{problems: list<string>, warnings: list<string>, title: string|null, city: string|null, intent_type: string|null, date: string|null, business_name: string|null, community_name: string|null}
     */
    public function plan(Profile $business, Profile $community, array $options = []): array
    {
        $problems = [];
        $warnings = [];

        $business->loadMissing('businessProfile.city');
        $community->loadMissing('communityProfile');

        $businessName = $business->businessProfile?->name;
        $communityName = $community->communityProfile?->name;

        if (! $business->isBusiness()) {
            $problems[] = 'The first account is not a business.';
        }
        if (! $community->isCommunity()) {
            $problems[] = 'The second account is not a community.';
        }
        foreach (['Business' => $business, 'Community' => $community] as $label => $profile) {
            if ($profile->trashed()) {
                $problems[] = "{$label} account is deleted.";
            } elseif ($profile->is_active === false) {
                $problems[] = "{$label} account is switched off.";
            }
            if ($profile->is_test_user) {
                $warnings[] = "{$label} account is a test user.";
            }
        }
        if ($problems === [] && $business->businessProfile === null) {
            $problems[] = 'Business has not finished onboarding (no business profile).';
        }
        if ($problems === [] && $community->communityProfile === null) {
            $problems[] = 'Community has not finished onboarding (no community profile).';
        }

        $payload = null;
        if ($problems === []) {
            if (! is_string($businessName) || trim($businessName) === '') {
                $problems[] = 'Business has no name.';
            } else {
                $payload = $this->autoListing->buildPayload($business);
                if (! is_string($payload['preferred_city'] ?? null) || trim($payload['preferred_city']) === '') {
                    $problems[] = 'Business has no city.';
                    $payload = null;
                }
            }
        }

        $date = null;
        if (isset($options['date']) && is_string($options['date']) && trim($options['date']) !== '') {
            try {
                $parsed = Carbon::createFromFormat('!Y-m-d', trim($options['date']));
            } catch (\Throwable) {
                $parsed = false;
            }
            if ($parsed === false || $parsed === null || $parsed->format('Y-m-d') !== trim($options['date'])) {
                $problems[] = 'Date must be YYYY-MM-DD.';
            } elseif ($parsed->lt(now()->startOfDay())) {
                $problems[] = 'Date is in the past.';
            } else {
                $date = $parsed->toDateString();
            }
        }

        if ($problems === [] && $this->openAutoKolabExists($business, $community)) {
            $problems[] = 'These two already have an open Kolab set up by Kolabing.';
        }

        $title = $this->titleFor($options['title'] ?? null, $payload['title'] ?? null);

        // ROLES §2.5: a business without an active subscription must not learn
        // the community's name, and the title is shown to it everywhere.
        if (is_string($title) && is_string($communityName) && trim($communityName) !== ''
            && $business->isBusiness() && ! $business->hasActiveSubscription()
            && str_contains(mb_strtolower($title), mb_strtolower(trim($communityName)))) {
            $problems[] = 'The title names the community, but this business has no active subscription and may not see community names. Change the title.';
        }

        return [
            'problems' => $problems,
            'warnings' => $warnings,
            'title' => $title,
            'city' => $payload['preferred_city'] ?? null,
            'intent_type' => $payload['intent_type'] ?? null,
            'date' => $date,
            'business_name' => $businessName,
            'community_name' => $communityName,
        ];
    }

    /**
     * Create the matched Kolab. Throws when plan() reports a problem.
     *
     * @param  array{date?: string|null, title?: string|null, notify?: bool}  $options
     * @return array{kolab: Kolab, application: Application, collaboration: Collaboration}
     *
     * @throws InvalidArgumentException
     */
    public function create(Profile $business, Profile $community, array $options = [], ?User $admin = null): array
    {
        $plan = $this->plan($business, $community, $options);

        if ($plan['problems'] !== []) {
            throw new InvalidArgumentException(implode(' ', $plan['problems']));
        }

        $date = $plan['date'];

        $result = DB::transaction(function () use ($business, $community, $plan, $date, $admin): array {
            $payload = $this->autoListing->buildPayload($business);
            $payload['title'] = (string) $plan['title'];

            $kolab = $this->kolabService->create($business, $payload);

            $kolab->forceFill([
                'status' => KolabStatus::Published,
                'published_at' => now(),
                'recipient_community_id' => $community->id,
                'is_auto_matched' => true,
                'created_by_admin_id' => $admin?->id,
                ...($date !== null ? [
                    'availability_mode' => 'one_time',
                    'availability_start' => $date,
                    'availability_end' => $date,
                ] : []),
            ])->save();
            $kolab->refresh();
            $this->notificationReminderService->syncKolabDraftReminder($kolab);

            $application = $this->applicationService->apply($community, $kolab, ['message' => null], notify: false);

            $accepted = $this->applicationService->accept(
                $application,
                ['scheduled_date' => $date],
                notify: false,
            );

            $this->chatService->threadForApplication($accepted['application']);

            return [
                'kolab' => $kolab->fresh(),
                'application' => $accepted['application'],
                'collaboration' => $accepted['collaboration'],
            ];
        });

        Log::info('Auto-kolab created', [
            'kolab_id' => $result['kolab']->id,
            'collaboration_id' => $result['collaboration']->id,
            'business_profile_id' => $business->id,
            'community_profile_id' => $community->id,
            'admin_id' => $admin?->id,
        ]);

        if (($options['notify'] ?? true) === true) {
            try {
                $this->notificationService->notifyAutoKolabCreated($result['collaboration']);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $result;
    }

    /**
     * Whether Kolabing already set up a Kolab between these two that has not
     * finished, so a re-run (or a double click) does not create a second one.
     */
    public function openAutoKolabExists(Profile $business, Profile $community): bool
    {
        return Kolab::query()
            ->autoMatched()
            ->where('creator_profile_id', $business->id)
            ->where('recipient_community_id', $community->id)
            ->whereHas('collaborations', fn ($q) => $q->whereIn('status', [
                CollaborationStatus::Scheduled->value,
                CollaborationStatus::Active->value,
            ]))
            ->exists();
    }

    /**
     * The maintainer's title, else the business's own listing title ("Host
     * your community at {name}" / "Partner with {name}"). The default never
     * names the community: a free business must not see it (ROLES §2.5).
     */
    private function titleFor(?string $title, ?string $listingTitle): ?string
    {
        if (is_string($title) && trim($title) !== '') {
            return Str::limit(trim($title), 255, '');
        }

        return is_string($listingTitle) && trim($listingTitle) !== '' ? $listingTitle : null;
    }

    private function resolveProfile(string $reference, UserType $type): Profile
    {
        $reference = trim($reference);
        $label = $type === UserType::Business ? 'business' : 'community';

        if ($reference === '') {
            throw new InvalidArgumentException("No {$label} given.");
        }

        if (Str::isUuid($reference)) {
            $profile = Profile::query()->withTrashed()->find($reference);
            if ($profile === null) {
                throw new InvalidArgumentException("No {$label} with id {$reference}.");
            }

            return $profile;
        }

        if (str_contains($reference, '@')) {
            $profile = Profile::query()
                ->whereRaw('lower(email) = ?', [mb_strtolower($reference)])
                ->where('user_type', $type)
                ->first();
            if ($profile === null) {
                throw new InvalidArgumentException("No {$label} with email {$reference}.");
            }

            return $profile;
        }

        $relation = $type === UserType::Business ? 'businessProfile' : 'communityProfile';
        $matches = Profile::query()
            ->where('user_type', $type)
            ->whereHas($relation, fn ($q) => $q->whereRaw('lower(name) = ?', [mb_strtolower($reference)]))
            ->limit(2)
            ->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        throw new InvalidArgumentException($matches->isEmpty()
            ? "No {$label} named \"{$reference}\". Use the profile id or email."
            : "More than one {$label} is named \"{$reference}\". Use the profile id or email.");
    }
}
