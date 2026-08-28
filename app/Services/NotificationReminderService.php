<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\EventSignupStatus;
use App\Enums\KolabStatus;
use App\Enums\MultiKolabEventStatus;
use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Event;
use App\Models\EventSignup;
use App\Models\Kolab;
use App\Models\MultiKolabEvent;
use App\Models\NotificationReminder;
use App\Models\Profile;
use Illuminate\Support\Carbon;

class NotificationReminderService
{
    /**
     * @var list<int>
     */
    private const CADENCE_HOURS = [2, 24, 72];

    /**
     * Incomplete-draft cap for Multi-Kolab Events (plan Task 8): exactly two
     * reminders — 24h, then a final one at 72h — never a third, and never
     * after publish/cancel (enforced by {@see refreshMultiKolabEventDraftReminder()}
     * re-checking `status === Draft` on every send attempt).
     *
     * @var list<int>
     */
    private const MULTI_KOLAB_EVENT_DRAFT_CADENCE_HOURS = [24, 72];

    /**
     * Event reminders are the only NEGATIVE cadence in this service, and that is
     * the whole trick: `scheduled_for = anchor_at->addHours($cadence[0])`, so
     * with the anchor at `events.starts_at` a cadence of `-24` schedules the
     * send 24 hours BEFORE the event rather than after it. No new command, no
     * new table, no sweep — the existing 15-minute cron already drains this.
     *
     * One hour of granularity is plenty at 15-minute cron resolution, and each
     * chain is a single step so it fires once and ends.
     *
     * @var list<int>
     */
    private const EVENT_REMINDER_24H_CADENCE_HOURS = [-24];

    /**
     * @var list<int>
     */
    private const EVENT_REMINDER_1H_CADENCE_HOURS = [-1];

    private const ENTITY_APPLICATION = 'application';

    private const ENTITY_KOLAB = 'kolab';

    private const ENTITY_COLLABORATION = 'collaboration';

    private const ENTITY_MULTI_KOLAB_EVENT = 'multi_kolab_event';

    private const ENTITY_EVENT = 'event';

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function syncKolabDraftReminder(Kolab $kolab): void
    {
        $this->syncReminder(
            profileId: $kolab->creator_profile_id,
            type: NotificationType::KolabCreateIncomplete,
            entityId: $kolab->id,
            entityType: self::ENTITY_KOLAB,
            eligible: $kolab->status === KolabStatus::Draft,
            anchorAt: $kolab->updated_at,
        );
    }

    public function cancelKolabDraftReminder(Kolab $kolab): void
    {
        $this->cancelReminder(
            profileId: $kolab->creator_profile_id,
            type: NotificationType::KolabCreateIncomplete,
            entityId: $kolab->id,
            entityType: self::ENTITY_KOLAB,
        );
    }

    public function syncMultiKolabEventDraftReminder(MultiKolabEvent $event): void
    {
        $this->syncReminder(
            profileId: $event->creator_profile_id,
            type: NotificationType::MultiKolabEventDraftIncomplete,
            entityId: $event->id,
            entityType: self::ENTITY_MULTI_KOLAB_EVENT,
            eligible: $event->status === MultiKolabEventStatus::Draft,
            anchorAt: $event->updated_at,
        );
    }

    public function cancelMultiKolabEventDraftReminder(MultiKolabEvent $event): void
    {
        $this->cancelReminder(
            profileId: $event->creator_profile_id,
            type: NotificationType::MultiKolabEventDraftIncomplete,
            entityId: $event->id,
            entityType: self::ENTITY_MULTI_KOLAB_EVENT,
        );
    }

    public function syncApplicationPendingReminder(Application $application): void
    {
        $application->loadMissing('kolab');
        $opportunity = $application->kolab;

        if ($opportunity === null) {
            return;
        }

        $this->syncReminder(
            profileId: $opportunity->creator_profile_id,
            type: NotificationType::ApplicationPending,
            entityId: $application->id,
            entityType: self::ENTITY_APPLICATION,
            eligible: $application->status === ApplicationStatus::Pending,
            anchorAt: $application->created_at,
        );
    }

    /**
     * Sync the review reminder for the business side of a just-completed collaboration.
     * Cancelled once that business leaves a review (see cancelReviewReminder()).
     */
    public function syncReviewReminder(Collaboration $collaboration): void
    {
        $businessProfile = $this->resolveBusinessProfile($collaboration);

        if ($businessProfile === null) {
            return;
        }

        $this->syncReminder(
            profileId: $businessProfile->id,
            type: NotificationType::ReviewReminder,
            entityId: $collaboration->id,
            entityType: self::ENTITY_COLLABORATION,
            eligible: $collaboration->status === CollaborationStatus::Completed,
            anchorAt: $collaboration->completed_at,
        );
    }

    public function cancelReviewReminder(Collaboration $collaboration, Profile $reviewer): void
    {
        $this->cancelReminder(
            profileId: $reviewer->id,
            type: NotificationType::ReviewReminder,
            entityId: $collaboration->id,
            entityType: self::ENTITY_COLLABORATION,
        );
    }

    /**
     * Sync the second-offer prompt after a business's first completed Kolab.
     * Cancelled once the business publishes a second offer.
     */
    public function syncSecondOfferPromptReminder(Collaboration $collaboration): void
    {
        $businessProfile = $this->resolveBusinessProfile($collaboration);

        if ($businessProfile === null) {
            return;
        }

        $publishedOfferCount = $businessProfile->kolabs()
            ->where('status', KolabStatus::Published)
            ->count();

        $this->syncReminder(
            profileId: $businessProfile->id,
            type: NotificationType::SecondOfferPrompt,
            entityId: $collaboration->id,
            entityType: self::ENTITY_COLLABORATION,
            eligible: $publishedOfferCount < 2,
            anchorAt: $collaboration->completed_at,
        );
    }

    private function resolveBusinessProfile(Collaboration $collaboration): ?Profile
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile']);

        return match (true) {
            $collaboration->creatorProfile->isBusiness() => $collaboration->creatorProfile,
            $collaboration->applicantProfile->isBusiness() => $collaboration->applicantProfile,
            default => null,
        };
    }

    public function syncUnreadMessageReminder(Application $application, Profile $recipient): void
    {
        $latestUnread = ChatMessage::query()
            ->where('application_id', $application->id)
            ->where('sender_profile_id', '!=', $recipient->id)
            ->whereNull('read_at')
            ->latest('created_at')
            ->first();

        $this->syncReminder(
            profileId: $recipient->id,
            type: NotificationType::UnreadMessage,
            entityId: $application->id,
            entityType: self::ENTITY_APPLICATION,
            eligible: $latestUnread !== null,
            anchorAt: $latestUnread?->created_at,
        );
    }

    public function sendDueReminders(int $limit = 100): int
    {
        $sentCount = 0;

        NotificationReminder::query()
            ->whereNull('cancelled_at')
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->get()
            ->each(function (NotificationReminder $reminder) use (&$sentCount): void {
                if (! $this->refreshReminderState($reminder)) {
                    return;
                }

                if ($reminder->scheduled_for === null || $reminder->scheduled_for->isFuture()) {
                    return;
                }

                $profile = Profile::query()->find($reminder->profile_id);
                if ($profile === null) {
                    $this->cancelExistingReminder($reminder);

                    return;
                }

                $payload = $this->buildPayload($reminder);
                if ($payload === null) {
                    $this->cancelExistingReminder($reminder);

                    return;
                }

                $this->notificationService->createNotification(
                    recipient: $profile,
                    type: $reminder->type,
                    title: $payload['title'],
                    body: $payload['body'],
                    targetId: $reminder->entity_id,
                    targetType: $reminder->entity_type,
                );

                $this->advanceReminder($reminder);
                $sentCount++;
            });

        return $sentCount;
    }

    private function syncReminder(
        string $profileId,
        NotificationType $type,
        string $entityId,
        string $entityType,
        bool $eligible,
        ?Carbon $anchorAt,
    ): void {
        $reminder = NotificationReminder::query()->firstOrNew([
            'profile_id' => $profileId,
            'type' => $type,
            'entity_id' => $entityId,
            'entity_type' => $entityType,
        ]);

        if (! $eligible || $anchorAt === null) {
            if (! $reminder->exists) {
                return;
            }

            $this->cancelExistingReminder($reminder);

            return;
        }

        $shouldReset = ! $reminder->exists
            || $reminder->cancelled_at !== null
            || $reminder->anchor_at === null
            || ! $reminder->anchor_at->equalTo($anchorAt)
            || $reminder->scheduled_for === null;

        if ($shouldReset) {
            $cadenceHours = $this->cadenceHoursFor($type);

            $reminder->fill([
                'anchor_at' => $anchorAt,
                'next_sequence' => 0,
                'last_sent_sequence' => null,
                'scheduled_for' => $anchorAt->copy()->addHours($cadenceHours[0]),
                'sent_at' => null,
                'cancelled_at' => null,
            ])->save();

            return;
        }

        $reminder->fill([
            'cancelled_at' => null,
        ])->save();
    }

    private function cancelReminder(
        string $profileId,
        NotificationType $type,
        string $entityId,
        string $entityType,
    ): void {
        $reminder = NotificationReminder::query()
            ->where('profile_id', $profileId)
            ->where('type', $type)
            ->where('entity_id', $entityId)
            ->where('entity_type', $entityType)
            ->first();

        if ($reminder === null) {
            return;
        }

        $this->cancelExistingReminder($reminder);
    }

    private function cancelExistingReminder(NotificationReminder $reminder): void
    {
        $reminder->update([
            'scheduled_for' => null,
            'cancelled_at' => now(),
        ]);
    }

    private function refreshReminderState(NotificationReminder $reminder): bool
    {
        return match ($reminder->type) {
            NotificationType::KolabCreateIncomplete => $this->refreshKolabDraftReminder($reminder),
            NotificationType::ApplicationPending => $this->refreshApplicationPendingReminder($reminder),
            NotificationType::UnreadMessage => $this->refreshUnreadMessageReminder($reminder),
            NotificationType::ReviewReminder => $this->refreshReviewReminder($reminder),
            NotificationType::SecondOfferPrompt => $this->refreshSecondOfferPromptReminder($reminder),
            NotificationType::MultiKolabEventDraftIncomplete => $this->refreshMultiKolabEventDraftReminder($reminder),
            NotificationType::EventReminder24h,
            NotificationType::EventReminder1h => $this->refreshEventReminder($reminder),
            default => false,
        };
    }

    private function refreshMultiKolabEventDraftReminder(NotificationReminder $reminder): bool
    {
        $event = MultiKolabEvent::query()->find($reminder->entity_id);

        // Re-checked on every send attempt — this is what guarantees "never
        // after publish/cancel": both transitions move status away from
        // Draft, so the very next due-check cancels the reminder outright
        // instead of sending.
        if ($event === null || $event->status !== MultiKolabEventStatus::Draft) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $this->syncReminder(
            profileId: $event->creator_profile_id,
            type: NotificationType::MultiKolabEventDraftIncomplete,
            entityId: $event->id,
            entityType: self::ENTITY_MULTI_KOLAB_EVENT,
            eligible: true,
            anchorAt: $event->updated_at,
        );

        $reminder->refresh();

        return true;
    }

    private function refreshReviewReminder(NotificationReminder $reminder): bool
    {
        $collaboration = Collaboration::query()->find($reminder->entity_id);

        if ($collaboration === null || $collaboration->status !== CollaborationStatus::Completed) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $alreadyReviewed = CollaborationReview::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('reviewer_profile_id', $reminder->profile_id)
            ->exists();

        if ($alreadyReviewed) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $this->syncReminder(
            profileId: $reminder->profile_id,
            type: NotificationType::ReviewReminder,
            entityId: $collaboration->id,
            entityType: self::ENTITY_COLLABORATION,
            eligible: true,
            anchorAt: $collaboration->completed_at,
        );

        $reminder->refresh();

        return true;
    }

    private function refreshSecondOfferPromptReminder(NotificationReminder $reminder): bool
    {
        $collaboration = Collaboration::query()->find($reminder->entity_id);
        $businessProfile = Profile::query()->find($reminder->profile_id);

        if ($collaboration === null || $businessProfile === null) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $publishedOfferCount = $businessProfile->kolabs()
            ->where('status', KolabStatus::Published)
            ->count();

        if ($publishedOfferCount >= 2) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $this->syncReminder(
            profileId: $businessProfile->id,
            type: NotificationType::SecondOfferPrompt,
            entityId: $collaboration->id,
            entityType: self::ENTITY_COLLABORATION,
            eligible: true,
            anchorAt: $collaboration->completed_at,
        );

        $reminder->refresh();

        return true;
    }

    /**
     * @return list<int>
     */
    /**
     * Schedule (or tear down) the 24h and 1h reminders for one sign-up.
     *
     * Called on sign-up, on cancellation, and on waitlist promotion. Safe to
     * call repeatedly: `syncReminder()` only resets a chain when the anchor
     * actually moved, so a rescheduled event re-times itself and an unchanged
     * one is left alone.
     */
    public function syncEventReminders(EventSignup $signup): void
    {
        $signup->loadMissing('event');
        $event = $signup->event;

        if ($event === null) {
            return;
        }

        $goingToAnUpcomingEvent = $signup->status === EventSignupStatus::Going
            && $event->starts_at !== null
            && $event->starts_at->isFuture();

        foreach ($this->eventReminderCadences() as [$type, $offsetHours]) {
            /*
             * What stops back-firing is refusing to CREATE a chain whose send
             * time is already behind us: someone signing up three hours before
             * the event must not get a "tomorrow" push on the next cron run.
             *
             * An existing, unsent chain is a different matter — it is allowed to
             * fire late. That is the catch-up path (a missed cron run, or an
             * event that moved closer), and it is why this method is also safe to
             * call from `refreshEventReminder()` moments before a send: an
             * already-due reminder must not cancel itself. The copy is computed
             * from the ACTUAL time left, so a late send never claims otherwise.
             *
             * The 1h chain is exempt from the create-guard on purpose: a sign-up
             * 40 minutes out deserves its one "starting soon" nudge.
             */
            $sendAt = $event->starts_at->copy()->addHours($offsetHours);
            $chainExists = NotificationReminder::query()
                ->where('profile_id', $signup->profile_id)
                ->where('type', $type)
                ->where('entity_id', $event->id)
                ->where('entity_type', self::ENTITY_EVENT)
                ->whereNull('cancelled_at')
                ->whereNull('sent_at')
                ->exists();

            $eligible = $goingToAnUpcomingEvent
                && ($chainExists || $offsetHours === -1 || $sendAt->isFuture());

            $this->syncReminder(
                profileId: $signup->profile_id,
                type: $type,
                entityId: $event->id,
                entityType: self::ENTITY_EVENT,
                eligible: $eligible,
                anchorAt: $event->starts_at,
            );
        }
    }

    /**
     * Re-sync every `going` sign-up on an event. Use after the event's start
     * time moves, or when it is cancelled/deleted.
     */
    public function syncEventRemindersForEvent(Event $event): void
    {
        EventSignup::query()
            ->where('event_id', $event->id)
            ->where('status', EventSignupStatus::Going->value)
            ->with('event')
            ->cursor()
            ->each(fn (EventSignup $signup) => $this->syncEventReminders($signup));
    }

    public function cancelEventReminders(string $eventId, string $profileId): void
    {
        foreach ($this->eventReminderCadences() as [$type, $_offsetHours]) {
            $this->cancelReminder(
                profileId: $profileId,
                type: $type,
                entityId: $eventId,
                entityType: self::ENTITY_EVENT,
            );
        }
    }

    /**
     * @return list<array{0: NotificationType, 1: int}> [type, hours offset from starts_at]
     */
    private function eventReminderCadences(): array
    {
        return [
            [NotificationType::EventReminder24h, self::EVENT_REMINDER_24H_CADENCE_HOURS[0]],
            [NotificationType::EventReminder1h, self::EVENT_REMINDER_1H_CADENCE_HOURS[0]],
        ];
    }

    /**
     * Re-derive an event reminder from live state right before it is sent, so a
     * withdrawn sign-up, a cancelled event or a moved start time is honoured
     * even if the chain row is stale.
     */
    private function refreshEventReminder(NotificationReminder $reminder): bool
    {
        $event = Event::query()->find($reminder->entity_id);

        if ($event === null) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $signup = EventSignup::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $reminder->profile_id)
            ->first();

        if ($signup === null || $signup->status !== EventSignupStatus::Going) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        // An opted-out attendee gets nothing at all — not even an in-app row.
        // Checked here rather than left to the push gate in NotificationService,
        // because a reminder that only exists in a list is not a reminder.
        $profile = Profile::query()->find($reminder->profile_id);

        if ($profile !== null && ! $this->notificationService->allowsPush($profile, $reminder->type)) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $signup->setRelation('event', $event);
        $this->syncEventReminders($signup);

        $reminder->refresh();

        return $reminder->cancelled_at === null;
    }

    private function cadenceHoursFor(NotificationType $type): array
    {
        return match ($type) {
            NotificationType::ReviewReminder => config('gamification_business.review_reminder_cadence_hours'),
            NotificationType::SecondOfferPrompt => config('gamification_business.second_offer_prompt_cadence_hours'),
            NotificationType::MultiKolabEventDraftIncomplete => self::MULTI_KOLAB_EVENT_DRAFT_CADENCE_HOURS,
            NotificationType::EventReminder24h => self::EVENT_REMINDER_24H_CADENCE_HOURS,
            NotificationType::EventReminder1h => self::EVENT_REMINDER_1H_CADENCE_HOURS,
            default => self::CADENCE_HOURS,
        };
    }

    private function refreshKolabDraftReminder(NotificationReminder $reminder): bool
    {
        $kolab = Kolab::query()->find($reminder->entity_id);

        if ($kolab === null || $kolab->status !== KolabStatus::Draft) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $this->syncReminder(
            profileId: $kolab->creator_profile_id,
            type: NotificationType::KolabCreateIncomplete,
            entityId: $kolab->id,
            entityType: self::ENTITY_KOLAB,
            eligible: true,
            anchorAt: $kolab->updated_at,
        );

        $reminder->refresh();

        return true;
    }

    private function refreshApplicationPendingReminder(NotificationReminder $reminder): bool
    {
        $application = Application::query()
            ->with('kolab')
            ->find($reminder->entity_id);

        if ($application === null || $application->status !== ApplicationStatus::Pending) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $opportunity = $application->kolab;
        if ($opportunity === null) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $this->syncReminder(
            profileId: $opportunity->creator_profile_id,
            type: NotificationType::ApplicationPending,
            entityId: $application->id,
            entityType: self::ENTITY_APPLICATION,
            eligible: true,
            anchorAt: $application->created_at,
        );

        $reminder->refresh();

        return true;
    }

    private function refreshUnreadMessageReminder(NotificationReminder $reminder): bool
    {
        $application = Application::query()->find($reminder->entity_id);
        $recipient = Profile::query()->find($reminder->profile_id);

        if ($application === null || $recipient === null) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $latestUnread = ChatMessage::query()
            ->where('application_id', $application->id)
            ->where('sender_profile_id', '!=', $recipient->id)
            ->whereNull('read_at')
            ->latest('created_at')
            ->first();

        if ($latestUnread === null) {
            $this->cancelExistingReminder($reminder);

            return false;
        }

        $this->syncReminder(
            profileId: $recipient->id,
            type: NotificationType::UnreadMessage,
            entityId: $application->id,
            entityType: self::ENTITY_APPLICATION,
            eligible: true,
            anchorAt: $latestUnread->created_at,
        );

        $reminder->refresh();

        return true;
    }

    /**
     * @return array{title: string, body: string}|null
     */
    /**
     * Copy for an event reminder, phrased from the ACTUAL time left rather than
     * from the reminder's nominal offset.
     *
     * The 1h chain can legitimately fire later than an hour out — a sign-up made
     * 40 minutes before the event schedules a send that is already due — so a
     * fixed "in 1 hour" would be a lie. The 15-minute cron can also land a
     * little past the mark.
     *
     * TODO(#252): localise to the recipient's profile locale (en / es / ca). The
     * app cannot translate a server-rendered push, so until this lands every
     * reminder arrives in English.
     */
    private function eventReminderPayload(NotificationReminder $reminder): ?array
    {
        $event = Event::query()->find($reminder->entity_id);

        if ($event === null || $event->starts_at === null) {
            return null;
        }

        $minutesLeft = (int) round(now()->diffInMinutes($event->starts_at, absolute: false));

        $title = match (true) {
            $minutesLeft <= 0 => 'Starting now',
            $minutesLeft < 90 => "Starting in {$minutesLeft} minutes",
            $minutesLeft < 60 * 20 => 'Starting in '.(int) round($minutesLeft / 60).' hours',
            default => 'Tomorrow',
        };

        return [
            'title' => $title,
            'body' => $event->name,
        ];
    }

    private function buildPayload(NotificationReminder $reminder): ?array
    {
        return match ($reminder->type) {
            NotificationType::EventReminder24h,
            NotificationType::EventReminder1h => $this->eventReminderPayload($reminder),
            NotificationType::KolabCreateIncomplete => [
                'title' => 'Finish your Kolab',
                'body' => "Your Kolab is still in draft. Complete it and publish when you're ready.",
            ],
            NotificationType::ApplicationPending => [
                'title' => 'You have a pending application',
                'body' => 'A new application is waiting for your review. Open it to accept or decline.',
            ],
            NotificationType::UnreadMessage => [
                'title' => 'You have an unread message',
                'body' => 'Someone sent you a message about your Kolab. Open the chat to reply.',
            ],
            NotificationType::ReviewReminder => [
                'title' => 'Share your experience',
                'body' => 'Your review helps future partners collaborate with confidence.',
            ],
            NotificationType::SecondOfferPrompt => [
                'title' => 'Ready for your next Kolab?',
                'body' => 'Build on the momentum and create your next offer.',
            ],
            NotificationType::MultiKolabEventDraftIncomplete => [
                'title' => 'Finish your event',
                'body' => 'Your Multi-Kolab Event is still in draft. Add roles and publish when ready.',
            ],
            default => null,
        };
    }

    private function advanceReminder(NotificationReminder $reminder): void
    {
        $cadenceHours = $this->cadenceHoursFor($reminder->type);
        $currentSequence = $reminder->next_sequence;
        $nextSequence = $currentSequence + 1;
        $now = now();

        while ($nextSequence < count($cadenceHours)
            && $reminder->anchor_at?->copy()->addHours($cadenceHours[$nextSequence])->lte($now)) {
            $nextSequence++;
        }

        $reminder->update([
            'last_sent_sequence' => $currentSequence,
            'next_sequence' => $nextSequence,
            'scheduled_for' => $nextSequence < count($cadenceHours)
                ? $reminder->anchor_at?->copy()->addHours($cadenceHours[$nextSequence])
                : null,
            'sent_at' => $now,
        ]);
    }
}
