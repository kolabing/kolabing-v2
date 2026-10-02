<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\Kolab;
use App\Models\KolabInvite;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Kolabing invites communities to apply to a Kolab (Daniel 2026-10-02: "we should
 * be able to invite communities through app notifications to send invites").
 *
 * A maintainer picks an open business offer (typically a business's auto listing)
 * and sends an in-app + push notification to communities in its city, optionally
 * of one category. The notification opens the Kolab in the app, where the
 * community sends its request through the normal apply flow.
 *
 * Guard rails:
 * - Only an offer a community can actually apply to today: published, a business's
 *   venue/product offer, not a direct proposal, not a Multi-Kolab role, owner
 *   active, a bookable date left.
 * - Only real communities: active, not deleted, not test users, onboarded, not the
 *   owner's blocker or blocked, not already applied.
 * - At most one invite per community per Kolab, ever (unique row), and at most
 *   `weekly cap` invites per community across all Kolabs in the last 7 days.
 * - A `limit` per send, largest communities first.
 * Push honours the community's `collaboration_updates` switch; the in-app row is
 * always written. No email.
 */
class KolabInviteService
{
    public const DEFAULT_LIMIT = 50;

    public const MAX_LIMIT = 500;

    public const DEFAULT_WEEKLY_CAP = 2;

    public const MAX_WEEKLY_CAP = 7;

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Find the Kolab to invite to: a Kolab id, or a business (profile id or
     * email) whose open auto listing — else newest open offer — is used.
     *
     * @throws InvalidArgumentException
     */
    public function resolveKolab(string $reference): Kolab
    {
        $reference = trim($reference);

        if ($reference === '') {
            throw new InvalidArgumentException('No Kolab given.');
        }

        if (Str::isUuid($reference)) {
            $kolab = Kolab::query()->find($reference);
            if ($kolab !== null) {
                return $kolab;
            }
        }

        $business = Profile::query()
            ->where('user_type', UserType::Business)
            ->where(fn (Builder $q) => Str::isUuid($reference)
                ? $q->where('id', $reference)
                : $q->whereRaw('lower(email) = ?', [mb_strtolower($reference)]))
            ->first();

        if ($business === null) {
            throw new InvalidArgumentException("No Kolab or business found for {$reference}.");
        }

        $kolab = $this->openOffersQuery()
            ->where('creator_profile_id', $business->id)
            ->orderByDesc('is_auto_listing')
            ->latest('published_at')
            ->first();

        if ($kolab === null) {
            throw new InvalidArgumentException("{$reference} has no open offer to invite communities to.");
        }

        return $kolab;
    }

    /**
     * Open business offers a maintainer can invite communities to, newest first.
     *
     * @return Collection<int, Kolab>
     */
    public function invitableKolabs(int $max = 500): Collection
    {
        return $this->openOffersQuery()
            ->with('creatorProfile.businessProfile')
            ->latest('published_at')
            ->limit($max)
            ->get()
            ->filter(fn (Kolab $kolab): bool => $kolab->hasSelectableDatesFrom(now()->startOfDay()))
            ->values();
    }

    /**
     * Why communities cannot be invited to this Kolab right now (empty = they can).
     *
     * @return list<string>
     */
    public function kolabProblems(Kolab $kolab): array
    {
        $kolab->loadMissing('creatorProfile');
        $owner = $kolab->creatorProfile;
        $problems = [];

        if ($kolab->status !== KolabStatus::Published) {
            $problems[] = 'The Kolab is not published.';
        }
        if (! in_array($kolab->intent_type, [IntentType::VenuePromotion, IntentType::ProductPromotion], true)) {
            $problems[] = 'Only a business offer (venue or product) takes community requests.';
        }
        if (is_string($kolab->recipient_community_id) && $kolab->recipient_community_id !== '') {
            $problems[] = 'The Kolab is a direct proposal to one community.';
        }
        if ($kolab->multi_kolab_event_id !== null) {
            $problems[] = 'The Kolab belongs to a Multi-Kolab event.';
        }
        if ($owner === null || $owner->trashed() || $owner->is_active === false) {
            $problems[] = 'The business is deleted or switched off.';
        }
        if ($problems === [] && ! $kolab->hasSelectableDatesFrom(now()->startOfDay())) {
            $problems[] = 'The Kolab has no bookable date left.';
        }

        return $problems;
    }

    /**
     * Who a send would reach, without writing anything.
     *
     * @param  array{city?: string|null, category?: string|null, limit?: int|null, weekly_cap?: int|null}  $filters
     * @return array{problems: list<string>, city: string, category: string|null, limit: int, weekly_cap: int, total_eligible: int, recipients: Collection<int, Profile>}
     */
    public function preview(Kolab $kolab, array $filters = []): array
    {
        $resolved = $this->resolveFilters($kolab, $filters);
        $problems = $this->kolabProblems($kolab);

        if ($problems !== []) {
            return [...$resolved, 'problems' => $problems, 'total_eligible' => 0, 'recipients' => new Collection];
        }

        $query = $this->candidates($kolab, $resolved);

        return [
            ...$resolved,
            'problems' => [],
            'total_eligible' => (clone $query)->count(),
            'recipients' => $this->largestFirst($query)->with('communityProfile')->limit($resolved['limit'])->get(),
        ];
    }

    /**
     * Invite the communities preview() lists. Each invite is its own row, so a
     * repeat or a concurrent send skips anyone already invited to this Kolab.
     *
     * @param  array{city?: string|null, category?: string|null, limit?: int|null, weekly_cap?: int|null}  $filters
     * @return array{invited: int, skipped: int, failed: int, total_eligible: int, city: string, category: string|null}
     *
     * @throws InvalidArgumentException when the Kolab cannot take invites
     */
    public function send(Kolab $kolab, array $filters = [], ?User $admin = null): array
    {
        $preview = $this->preview($kolab, $filters);

        if ($preview['problems'] !== []) {
            throw new InvalidArgumentException(implode(' ', $preview['problems']));
        }

        $invited = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($preview['recipients'] as $community) {
            try {
                $invite = KolabInvite::query()->create([
                    'kolab_id' => $kolab->id,
                    'profile_id' => $community->id,
                    'sent_by_admin_id' => $admin?->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                $skipped++;

                continue;
            }

            try {
                $notification = $this->notificationService->notifyKolabInvite($community, $kolab);
                $invite->forceFill(['notification_id' => $notification->id])->save();
                $invited++;
            } catch (\Throwable $e) {
                $invite->delete();
                $failed++;
                report($e);
            }
        }

        Log::info('Kolab invites sent', [
            'kolab_id' => $kolab->id,
            'invited' => $invited,
            'skipped' => $skipped,
            'failed' => $failed,
            'city' => $preview['city'],
            'category' => $preview['category'],
            'admin_id' => $admin?->id,
        ]);

        return [
            'invited' => $invited,
            'skipped' => $skipped,
            'failed' => $failed,
            'total_eligible' => $preview['total_eligible'],
            'city' => $preview['city'],
            'category' => $preview['category'],
        ];
    }

    /**
     * @param  array{city?: string|null, category?: string|null, limit?: int|null, weekly_cap?: int|null}  $filters
     * @return array{city: string, category: string|null, limit: int, weekly_cap: int}
     */
    private function resolveFilters(Kolab $kolab, array $filters): array
    {
        $city = isset($filters['city']) && is_string($filters['city']) && trim($filters['city']) !== ''
            ? trim($filters['city'])
            : (string) $kolab->preferred_city;
        $category = isset($filters['category']) && is_string($filters['category']) && trim($filters['category']) !== ''
            ? trim($filters['category'])
            : null;

        return [
            'city' => $city,
            'category' => $category,
            'limit' => max(1, min(self::MAX_LIMIT, (int) ($filters['limit'] ?? self::DEFAULT_LIMIT))),
            'weekly_cap' => max(1, min(self::MAX_WEEKLY_CAP, (int) ($filters['weekly_cap'] ?? self::DEFAULT_WEEKLY_CAP))),
        ];
    }

    /**
     * Communities eligible for this send, largest first.
     *
     * @param  array{city: string, category: string|null, limit: int, weekly_cap: int}  $filters
     * @return Builder<Profile>
     */
    private function candidates(Kolab $kolab, array $filters): Builder
    {
        $ownerId = $kolab->creator_profile_id;
        $city = mb_strtolower($filters['city']);

        return Profile::query()
            ->where('user_type', UserType::Community)
            ->active()
            ->where('is_test_user', false)
            ->where('profiles.id', '!=', $ownerId)
            ->whereHas('communityProfile', function (Builder $q) use ($city, $filters): void {
                $q->whereHas('city', fn (Builder $c) => $c->whereRaw('lower(name) = ?', [$city]));
                if ($filters['category'] !== null) {
                    $q->where('community_type', $filters['category']);
                }
            })
            ->whereNotIn('profiles.id', KolabInvite::query()->select('profile_id')->where('kolab_id', $kolab->id))
            ->whereNotIn('profiles.id', Application::query()->select('applicant_profile_id')->where('kolab_id', $kolab->id))
            ->whereNotIn('profiles.id', KolabInvite::query()
                ->select('profile_id')
                ->where('created_at', '>=', now()->subDays(7))
                ->groupBy('profile_id')
                ->havingRaw('count(*) >= ?', [$filters['weekly_cap']]))
            ->whereNotIn('profiles.id', UserBlock::query()->select('blocked_profile_id')->where('blocker_profile_id', $ownerId))
            ->whereNotIn('profiles.id', UserBlock::query()->select('blocker_profile_id')->where('blocked_profile_id', $ownerId));
    }

    /**
     * Largest communities first (unknown size last), then oldest accounts.
     * Kept apart from candidates() so the count query carries no ORDER BY,
     * which Postgres rejects next to an aggregate.
     *
     * @param  Builder<Profile>  $query
     * @return Builder<Profile>
     */
    private function largestFirst(Builder $query): Builder
    {
        $size = '(select community_size from community_profiles where community_profiles.profile_id = profiles.id limit 1)';

        return $query
            ->orderByRaw("{$size} is null")
            ->orderByRaw("{$size} desc")
            ->orderBy('profiles.created_at');
    }

    /**
     * @return Builder<Kolab>
     */
    private function openOffersQuery(): Builder
    {
        return Kolab::query()
            ->where('status', KolabStatus::Published)
            ->whereIn('intent_type', [IntentType::VenuePromotion, IntentType::ProductPromotion])
            ->whereNull('recipient_community_id')
            ->whereNull('multi_kolab_event_id')
            ->fromActiveOwner();
    }
}
