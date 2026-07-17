<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Kolab;
use App\Models\NotificationReminder;
use App\Models\Profile;
use Illuminate\Support\Carbon;

class NotificationReminderService
{
    /**
     * @var list<int>
     */
    private const CADENCE_HOURS = [2, 24, 72];

    private const ENTITY_APPLICATION = 'application';

    private const ENTITY_KOLAB = 'kolab';

    private const ENTITY_COLLABORATION = 'collaboration';

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
            default => false,
        };
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
    private function cadenceHoursFor(NotificationType $type): array
    {
        return match ($type) {
            NotificationType::ReviewReminder => config('gamification_business.review_reminder_cadence_hours'),
            NotificationType::SecondOfferPrompt => config('gamification_business.second_offer_prompt_cadence_hours'),
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
    private function buildPayload(NotificationReminder $reminder): ?array
    {
        return match ($reminder->type) {
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
