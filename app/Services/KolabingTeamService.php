<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChatSenderType;
use App\Enums\KolabingTeamOfferKind;
use App\Enums\KolabingTeamOfferStatus;
use App\Enums\KolabStatus;
use App\Enums\NotificationType;
use App\Events\NewChatMessage;
use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Kolab;
use App\Models\KolabingTeamOffer;
use App\Models\Profile;
use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Kolabing speaks as itself (SERR-658, spec agreed on SERR-656).
 *
 * A maintainer drafts an offer for ONE profile, reads it, and sends it: one
 * row = one send, every row stays in `kolabing_team_offers`. The offer lands in
 * the recipient's "Kolabing team" thread as a message with an offer card
 * (Accept / Not now), plus an in-app notification and a push.
 *
 * Two kinds:
 * - community_apply: a community is offered an open business Kolab. Accept runs
 *   the normal apply flow ({@see ApplicationService::apply}).
 * - business_propose: a business is offered a direct proposal to a named
 *   community. Accept creates that Kolab with `recipient_community_id` set and
 *   WITHOUT the subscription check — scoped to this class: the bypass lives here
 *   (the Kolab is written directly, not through KolabService::publish, exactly
 *   as AutoKolabService does) and nowhere in the public publish path.
 *
 * Guard rails (the KolabInviteService set): a unique row per recipient per
 * offer, a weekly cap per recipient, and never an inactive, deleted or test
 * account, nor either side of a block. The push honours the recipient's
 * `collaboration_updates` switch; the in-app row is always written.
 *
 * Accounts that never opened the app get the same offer by email as well; the
 * thread message is still written, so the offer is waiting when they sign in.
 */
class KolabingTeamService
{
    public const DEFAULT_WEEKLY_CAP = 2;

    public function __construct(
        private readonly ChatService $chatService,
        private readonly NotificationService $notificationService,
        private readonly KolabInviteService $kolabInvites,
        private readonly KolabService $kolabService,
        private readonly ApplicationService $applicationService,
        private readonly BusinessAutoListingService $autoListing,
        private readonly EmailService $emailService,
    ) {}

    /**
     * Draft one offer. Nothing reaches the recipient until send().
     *
     * @param  array{kind: string, recipient: string, kolab?: string|null, community?: string|null, title?: string|null, message?: string|null, what_business_gets?: string|null, what_community_gets?: string|null, proposed_date?: string|null, test_send?: bool|null}  $data
     *
     * @throws InvalidArgumentException
     */
    public function draft(array $data, ?User $admin = null): KolabingTeamOffer
    {
        $kind = KolabingTeamOfferKind::tryFrom((string) ($data['kind'] ?? ''))
            ?? throw new InvalidArgumentException('Unknown offer kind.');

        $recipient = Profile::withTrashed()->find($data['recipient'] ?? null)
            ?? throw new InvalidArgumentException('Recipient not found.');

        $kolab = null;
        $community = null;

        if ($kind === KolabingTeamOfferKind::CommunityApply) {
            if (! $recipient->isCommunity()) {
                throw new InvalidArgumentException('A Kolab offer goes to a community.');
            }
            $kolab = Kolab::query()->find($data['kolab'] ?? null)
                ?? throw new InvalidArgumentException('Pick the Kolab to offer.');
        } else {
            if (! $recipient->isBusiness()) {
                throw new InvalidArgumentException('A proposal offer goes to a business.');
            }
            $community = Profile::withTrashed()->find($data['community'] ?? null)
                ?? throw new InvalidArgumentException('Pick the community to propose.');
            if (! $community->isCommunity()) {
                throw new InvalidArgumentException('The named profile is not a community.');
            }
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = $kolab?->title
                ?? 'A Kolab with '.($community?->getExtendedProfile()?->name ?? 'a local community');
        }

        $date = $data['proposed_date'] ?? null;
        $date = is_string($date) && trim($date) !== '' ? $this->parseDate($date) : null;

        $dedupeKey = implode(':', [$recipient->id, $kind->value, $kolab?->id ?? $community?->id]);

        if (KolabingTeamOffer::query()->where('dedupe_key', $dedupeKey)->exists()) {
            throw new InvalidArgumentException('This recipient already has this offer.');
        }

        $offer = KolabingTeamOffer::query()->create([
            'kind' => $kind,
            'status' => KolabingTeamOfferStatus::Draft,
            'recipient_profile_id' => $recipient->id,
            'kolab_id' => $kolab?->id,
            'community_profile_id' => $community?->id,
            'title' => mb_substr($title, 0, 255),
            'message' => $this->nullIfBlank($data['message'] ?? null),
            'what_business_gets' => $this->nullIfBlank($data['what_business_gets'] ?? null),
            'what_community_gets' => $this->nullIfBlank($data['what_community_gets'] ?? null),
            'proposed_date' => $date,
            'is_test_send' => (bool) ($data['test_send'] ?? false),
            'channel' => 'thread',
            'dedupe_key' => $dedupeKey,
            'created_by_admin_id' => $admin?->id,
        ]);

        return $offer->load(['recipient', 'kolab', 'communityProfile']);
    }

    /**
     * Why this draft cannot be sent right now (empty = it can).
     *
     * @return list<string>
     */
    public function problems(KolabingTeamOffer $offer): array
    {
        $offer->loadMissing(['recipient.businessProfile', 'recipient.communityProfile', 'kolab', 'communityProfile']);
        $problems = [];

        if ($offer->status !== KolabingTeamOfferStatus::Draft) {
            return ['This offer was already '.$offer->status->value.'.'];
        }

        $recipient = $offer->recipient;
        foreach ($this->profileProblems($recipient, 'Recipient', $offer->is_test_send) as $problem) {
            $problems[] = $problem;
        }

        $weeklyCap = self::DEFAULT_WEEKLY_CAP;
        $recent = KolabingTeamOffer::query()
            ->where('recipient_profile_id', $recipient->id)
            ->where('is_test_send', false)
            ->whereNotNull('sent_at')
            ->where('sent_at', '>=', now()->subDays(7))
            ->count();
        if (! $offer->is_test_send && $recent >= $weeklyCap) {
            $problems[] = "Recipient already got {$recent} Kolabing team offers in the last 7 days (cap {$weeklyCap}).";
        }

        if ($offer->kind === KolabingTeamOfferKind::CommunityApply) {
            $kolab = $offer->kolab;
            if ($kolab === null) {
                $problems[] = 'The Kolab no longer exists.';
            } else {
                array_push($problems, ...$this->kolabInvites->kolabProblems($kolab));
                if (Application::query()->where('kolab_id', $kolab->id)->where('applicant_profile_id', $recipient->id)->exists()) {
                    $problems[] = 'The community already applied to this Kolab.';
                }
                if ($this->isBlockedPair($kolab->creator_profile_id, $recipient->id)) {
                    $problems[] = 'The business and the community have blocked each other.';
                }
            }
        } else {
            $community = $offer->communityProfile;
            if ($community === null) {
                $problems[] = 'The named community no longer exists.';
            } else {
                foreach ($this->profileProblems($community, 'Community', $offer->is_test_send) as $problem) {
                    $problems[] = $problem;
                }
                if ($this->isBlockedPair($recipient->id, $community->id)) {
                    $problems[] = 'The business and the community have blocked each other.';
                }
            }
            if ($recipient->businessProfile === null) {
                $problems[] = 'The business has not finished onboarding.';
            }
            if ($offer->proposed_date !== null && $offer->proposed_date->lt(now()->startOfDay())) {
                $problems[] = 'The proposed date is in the past.';
            }
        }

        return $problems;
    }

    /**
     * Send one approved draft. The row is locked and flipped draft -> sent
     * inside the transaction, so a double click or a retry cannot send twice.
     *
     * @throws InvalidArgumentException when the offer cannot be sent
     */
    public function send(KolabingTeamOffer $offer, User $admin): KolabingTeamOffer
    {
        $sent = DB::transaction(function () use ($offer, $admin): KolabingTeamOffer {
            $locked = KolabingTeamOffer::query()->lockForUpdate()->findOrFail($offer->id);

            $problems = $this->problems($locked);
            if ($problems !== []) {
                throw new InvalidArgumentException(implode(' ', $problems));
            }

            $recipient = $locked->recipient;
            $reachable = $this->hasOpenedTheApp($recipient);
            $thread = $this->chatService->teamThreadFor($recipient);

            $message = ChatMessage::query()->create([
                'thread_id' => $thread->id,
                'sender_profile_id' => null,
                'sender_type' => ChatSenderType::Team,
                'sender_user_id' => $admin->id,
                'team_offer_id' => $locked->id,
                'content' => $this->messageText($locked),
            ]);

            $thread->forceFill(['last_message_at' => now()])->save();

            $locked->forceFill([
                'status' => KolabingTeamOfferStatus::Sent,
                'channel' => $reachable ? 'thread' : 'email',
                'sent_by_admin_id' => $admin->id,
                'chat_message_id' => $message->id,
                'sent_at' => now(),
            ])->save();

            return $locked;
        });

        $this->announce($sent);

        Log::info('Kolabing team offer sent', [
            'offer_id' => $sent->id,
            'kind' => $sent->kind->value,
            'recipient_profile_id' => $sent->recipient_profile_id,
            'channel' => $sent->channel,
            'admin_id' => $admin->id,
        ]);

        return $sent->fresh(['recipient', 'kolab', 'communityProfile']);
    }

    /**
     * A free-text message from the team into a profile's thread (an answer to
     * a reply). Same push rules as an offer.
     */
    public function reply(Profile $recipient, string $content, User $admin): ChatMessage
    {
        $content = trim($content);
        if ($content === '') {
            throw new InvalidArgumentException('Write a message first.');
        }

        $thread = $this->chatService->teamThreadFor($recipient);

        $message = ChatMessage::query()->create([
            'thread_id' => $thread->id,
            'sender_profile_id' => null,
            'sender_type' => ChatSenderType::Team,
            'sender_user_id' => $admin->id,
            'content' => $content,
        ]);
        $thread->forceFill(['last_message_at' => now(), 'admin_last_read_at' => now()])->save();

        $this->broadcast($message);
        $this->push($recipient, $thread, $content);

        return $message;
    }

    /**
     * The recipient says yes.
     *
     * @return array{offer: KolabingTeamOffer, kolab: Kolab|null, application: Application|null}
     *
     * @throws InvalidArgumentException when it is not theirs, not open, or no longer valid
     */
    public function accept(KolabingTeamOffer $offer, Profile $actor): array
    {
        return DB::transaction(function () use ($offer, $actor): array {
            $locked = $this->lockOpenOffer($offer, $actor);

            if ($locked->kind === KolabingTeamOfferKind::CommunityApply) {
                $kolab = $locked->kolab;
                if ($kolab === null) {
                    throw new InvalidArgumentException('This Kolab is no longer available.');
                }
                $application = $this->applicationService->apply($actor, $kolab, ['message' => null]);
                $locked->forceFill(['result_application_id' => $application->id, 'result_kolab_id' => $kolab->id]);
                $created = null;
            } else {
                $created = $this->createDirectProposal($locked, $actor);
                $locked->forceFill(['result_kolab_id' => $created->id]);
                $application = null;
            }

            $locked->forceFill(['status' => KolabingTeamOfferStatus::Accepted, 'responded_at' => now()])->save();

            return ['offer' => $locked->fresh(), 'kolab' => $created ?? $locked->kolab, 'application' => $application];
        });
    }

    /**
     * The recipient says "not now". Final for this offer.
     *
     * @throws InvalidArgumentException
     */
    public function decline(KolabingTeamOffer $offer, Profile $actor): KolabingTeamOffer
    {
        return DB::transaction(function () use ($offer, $actor): KolabingTeamOffer {
            $locked = $this->lockOpenOffer($offer, $actor);
            $locked->forceFill(['status' => KolabingTeamOfferStatus::Declined, 'responded_at' => now()])->save();

            return $locked->fresh();
        });
    }

    /**
     * Whether the account has ever used the app: the in-app channel reaches
     * these. Everyone else also gets the email.
     */
    public function hasOpenedTheApp(Profile $profile): bool
    {
        return $profile->last_active_at !== null || ! empty($profile->device_token);
    }

    /**
     * A `wa.me` link a maintainer can click to nudge the recipient on
     * WhatsApp (opens their own WhatsApp with the text typed; nothing is sent
     * by the platform). Null without a number.
     */
    public function whatsappLink(KolabingTeamOffer $offer): ?string
    {
        $offer->loadMissing('recipient.businessProfile');
        $raw = $offer->recipient->phone_number
            ?? ($offer->recipient->businessProfile?->primary_venue['phone_number'] ?? null);
        $number = preg_replace('/\D+/', '', (string) $raw) ?? '';

        if ($number === '') {
            return null;
        }

        return 'https://wa.me/'.$number.'?text='.rawurlencode($this->fallbackText($offer));
    }

    private function lockOpenOffer(KolabingTeamOffer $offer, Profile $actor): KolabingTeamOffer
    {
        $locked = KolabingTeamOffer::query()->lockForUpdate()->findOrFail($offer->id);

        if ($locked->recipient_profile_id !== $actor->id) {
            throw new InvalidArgumentException('This offer is not yours.');
        }
        if (! $locked->isOpenForResponse()) {
            throw new InvalidArgumentException('This offer was already answered.');
        }

        return $locked;
    }

    /**
     * Business Accept: the direct proposal to the named community, without the
     * subscription check. Written directly (not through KolabService::publish)
     * the way AutoKolabService does, so the public paywall path stays untouched.
     */
    private function createDirectProposal(KolabingTeamOffer $offer, Profile $business): Kolab
    {
        $community = $offer->communityProfile;
        if ($community === null || ! $community->isCommunity() || $community->trashed() || $community->is_active === false) {
            throw new InvalidArgumentException('This community is no longer available.');
        }

        $payload = $this->autoListing->buildPayload($business);
        $payload['title'] = $offer->title;

        $kolab = $this->kolabService->create($business, $payload);

        $date = $offer->proposed_date?->toDateString();
        $kolab->forceFill([
            'status' => KolabStatus::Published,
            'published_at' => now(),
            'recipient_community_id' => $community->id,
            'created_by_admin_id' => $offer->sent_by_admin_id,
            ...($date !== null ? [
                'availability_mode' => 'one_time',
                'availability_start' => $date,
                'availability_end' => $date,
            ] : []),
        ])->save();
        $kolab->refresh();

        try {
            $this->notificationService->notifyKolabInvite($community, $kolab);
        } catch (\Throwable $e) {
            report($e);
        }

        return $kolab;
    }

    private function announce(KolabingTeamOffer $offer): void
    {
        $message = ChatMessage::query()->with('teamOffer')->find($offer->chat_message_id);
        $recipient = $offer->recipient;

        if ($message !== null) {
            $this->broadcast($message);
        }

        $thread = $this->chatService->teamThreadFor($recipient);

        try {
            $notification = $this->push($recipient, $thread, $offer->title);
            $offer->forceFill(['notification_id' => $notification->id])->save();
        } catch (\Throwable $e) {
            report($e);
        }

        if ($offer->channel === 'email' && ! $offer->is_test_send && is_string($recipient->email) && $recipient->email !== '') {
            try {
                $this->emailService->sendRawToAddress(
                    $recipient->email,
                    'Kolabing has an offer for you',
                    nl2br(e($this->fallbackText($offer))),
                    $this->fallbackText($offer),
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function push(Profile $recipient, ChatThread $thread, string $preview): \App\Models\Notification
    {
        $locale = $recipient->preferred_locale ?? config('app.fallback_locale');

        return $this->notificationService->createNotification(
            recipient: $recipient,
            type: NotificationType::KolabingTeamMessage,
            title: __('notifications.kolabing_team.title', [], $locale),
            body: mb_substr($preview, 0, 140),
            targetId: $thread->id,
            targetType: 'chat_thread',
        );
    }

    private function broadcast(ChatMessage $message): void
    {
        try {
            broadcast(new NewChatMessage($message->load('teamOffer')))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function messageText(KolabingTeamOffer $offer): string
    {
        $intro = $offer->message ?? 'Kolabing has a Kolab idea for you.';

        return $intro;
    }

    private function fallbackText(KolabingTeamOffer $offer): string
    {
        $link = (string) (config('webapp.play_store_url') ?: config('app.url'));

        return trim(($offer->message ?? 'Kolabing has a Kolab idea for you.')
            .PHP_EOL.PHP_EOL.$offer->title
            .PHP_EOL.PHP_EOL.'Open the Kolabing app and look for the "Kolabing team" chat to accept it: '.$link);
    }

    /**
     * @return list<string>
     */
    private function profileProblems(Profile $profile, string $label, bool $testSend = false): array
    {
        $problems = [];

        if ($profile->trashed()) {
            $problems[] = "{$label} account is deleted.";
        } elseif ($profile->is_active === false) {
            $problems[] = "{$label} account is switched off.";
        }
        // Real sends never reach a test account, and a test send reaches nothing else.
        if ($profile->is_test_user && ! $testSend) {
            $problems[] = "{$label} account is a test user.";
        }
        if ($testSend && ! $profile->is_test_user) {
            $problems[] = "{$label} account is not a test user; a test send may only go to test accounts.";
        }

        return $problems;
    }

    private function isBlockedPair(string $a, string $b): bool
    {
        return UserBlock::query()
            ->where(fn ($q) => $q->where('blocker_profile_id', $a)->where('blocked_profile_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_profile_id', $b)->where('blocked_profile_id', $a))
            ->exists();
    }

    private function parseDate(string $date): Carbon
    {
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', trim($date));
        } catch (\Throwable) {
            $parsed = false;
        }

        if ($parsed === false || $parsed === null || $parsed->format('Y-m-d') !== trim($date)) {
            throw new InvalidArgumentException('Date must be YYYY-MM-DD.');
        }

        return $parsed;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
