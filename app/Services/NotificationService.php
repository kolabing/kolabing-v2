<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
use App\Models\Application;
use App\Models\ChallengeCompletion;
use App\Models\ChatMessage;
use App\Models\Collaboration;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class NotificationService
{
    /**
     * Get paginated notifications for a profile.
     *
     * @return LengthAwarePaginator<Notification>
     */
    public function getNotifications(Profile $profile, int $perPage = 20): LengthAwarePaginator
    {
        return Notification::query()
            ->where('profile_id', $profile->id)
            ->with(['actorProfile.businessProfile', 'actorProfile.communityProfile'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get the count of unread notifications for a profile.
     */
    public function getUnreadCount(Profile $profile): int
    {
        return Notification::query()
            ->where('profile_id', $profile->id)
            ->unread()
            ->count();
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Notification $notification): Notification
    {
        $notification->update(['read_at' => now()]);

        return $notification;
    }

    /**
     * Mark all unread notifications as read for a profile.
     */
    public function markAllAsRead(Profile $profile): int
    {
        return Notification::query()
            ->where('profile_id', $profile->id)
            ->unread()
            ->update(['read_at' => now()]);
    }

    /**
     * Create a notification record and dispatch a push notification for the recipient.
     */
    public function createNotification(
        Profile $recipient,
        NotificationType $type,
        string $title,
        string $body,
        ?Profile $actor = null,
        ?string $targetId = null,
        ?string $targetType = null,
        array $pushOptions = [],
    ): Notification {
        $notification = Notification::create([
            'profile_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'actor_profile_id' => $actor?->id,
            'target_id' => $targetId,
            'target_type' => $targetType,
        ]);

        SendPushNotification::dispatch($recipient, $title, $body, $type, $targetId, $pushOptions);

        return $notification;
    }

    /**
     * Bulk-record a notification for many recipients in one (chunked) insert —
     * scale-friendly fan-out (the push is sent separately, in one multi-recipient
     * call by the caller). Does NOT dispatch per-recipient push jobs.
     *
     * @param  array<int, string>  $recipientIds
     */
    public function recordNotifications(
        array $recipientIds,
        NotificationType $type,
        string $title,
        string $body,
        ?string $actorProfileId = null,
        ?string $targetId = null,
        ?string $targetType = null,
    ): void {
        if ($recipientIds === []) {
            return;
        }

        $now = now();
        $rows = array_map(static fn (string $profileId): array => [
            'id' => (string) Str::uuid(),
            'profile_id' => $profileId,
            'type' => $type->value,
            'title' => $title,
            'body' => $body,
            'actor_profile_id' => $actorProfileId,
            'target_id' => $targetId,
            'target_type' => $targetType,
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ], array_values($recipientIds));

        foreach (array_chunk($rows, 500) as $chunk) {
            Notification::query()->insert($chunk);
        }
    }

    /**
     * Of the given profile ids, those who allow chat-message notifications.
     * Missing preference row = default ON.
     *
     * @param  array<int, string>  $profileIds
     * @return array<int, string>
     */
    public function recipientsAllowingMessages(array $profileIds): array
    {
        if ($profileIds === []) {
            return [];
        }

        $optedOut = \App\Models\NotificationPreference::query()
            ->whereIn('profile_id', $profileIds)
            ->where('message_notifications', false)
            ->pluck('profile_id')
            ->all();

        return array_values(array_diff($profileIds, $optedOut));
    }

    /**
     * Create a notification for a new chat message.
     * Notifies the other party in the application conversation.
     */
    public function notifyNewMessage(ChatMessage $message, Application $application): void
    {
        $application->loadMissing([
            'collabOpportunity.creatorProfile',
            'applicantProfile',
        ]);

        $senderProfileId = $message->sender_profile_id;

        // Determine recipient: if sender is applicant, notify creator; otherwise notify applicant
        $recipient = $senderProfileId === $application->applicant_profile_id
            ? $application->collabOpportunity->creatorProfile
            : $application->applicantProfile;

        $senderProfile = $senderProfileId === $application->applicant_profile_id
            ? $application->applicantProfile
            : $application->collabOpportunity->creatorProfile;

        // Respect the recipient's message-notification preference (default ON).
        if ($this->recipientsAllowingMessages([$recipient->id]) === []) {
            return;
        }

        $body = Str::limit($message->content, 100, '...');

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::NewMessage,
            title: 'New Message',
            body: $body,
            actor: $senderProfile,
            targetId: $application->id,
            targetType: 'application',
        );
    }

    /**
     * Create a notification when an application is received.
     * Notifies the opportunity owner.
     */
    public function notifyApplicationReceived(Application $application): void
    {
        $application->loadMissing([
            'collabOpportunity.creatorProfile',
            'applicantProfile.businessProfile',
            'applicantProfile.communityProfile',
        ]);

        $recipient = $application->collabOpportunity->creatorProfile;
        $actor = $application->applicantProfile;
        $actorName = $actor->getExtendedProfile()?->name ?? 'Someone';
        $opportunityTitle = $application->collabOpportunity->title;

        $body = "{$actorName} applied to your \"{$opportunityTitle}\" opportunity.";

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationReceived,
            title: 'New Application',
            body: $body,
            actor: $actor,
            targetId: $application->id,
            targetType: 'application',
        );
    }

    /**
     * Create a notification when an application is accepted.
     * Notifies the applicant.
     */
    public function notifyApplicationAccepted(Application $application): void
    {
        $application->loadMissing([
            'collabOpportunity.creatorProfile',
            'applicantProfile',
        ]);

        $recipient = $application->applicantProfile;
        $actor = $application->collabOpportunity->creatorProfile;
        $opportunityTitle = $application->collabOpportunity->title;

        $body = "Your application for \"{$opportunityTitle}\" has been accepted!";

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationAccepted,
            title: 'Application Accepted',
            body: $body,
            actor: $actor,
            targetId: $application->id,
            targetType: 'application',
        );
    }

    /**
     * Create a notification when an application is declined.
     * Notifies the applicant.
     */
    public function notifyApplicationDeclined(Application $application): void
    {
        $application->loadMissing([
            'collabOpportunity.creatorProfile',
            'applicantProfile',
        ]);

        $recipient = $application->applicantProfile;
        $actor = $application->collabOpportunity->creatorProfile;
        $opportunityTitle = $application->collabOpportunity->title;

        $body = "Your application for \"{$opportunityTitle}\" was declined.";

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationDeclined,
            title: 'Application Declined',
            body: $body,
            actor: $actor,
            targetId: $application->id,
            targetType: 'application',
        );
    }

    /**
     * Notify when a challenge is verified (awarded to the challenger).
     */
    public function notifyChallengeVerified(ChallengeCompletion $completion): void
    {
        $completion->loadMissing(['challenge', 'challenger', 'verifier']);

        $this->createNotification(
            recipient: $completion->challenger,
            type: NotificationType::ChallengeVerified,
            title: 'Challenge Verified!',
            body: "Your \"{$completion->challenge->name}\" challenge was verified. You earned {$completion->points_earned} points!",
            actor: $completion->verifier,
            targetId: $completion->id,
            targetType: 'challenge_completion',
        );
    }

    /**
     * Send "Today's your Kolab 🎉" reminder to both parties.
     * Idempotent: skips a party if a reminder of this type was already sent
     * for this collaboration (checked via existing notifications rows).
     */
    public function notifyCollabDayReminder(Collaboration $collaboration): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'collabOpportunity']);

        $title = "Today's your Kolab! 🎉";
        $body = 'Your collaboration is happening today. Have a great Kolab!';

        foreach ([$collaboration->creatorProfile, $collaboration->applicantProfile] as $profile) {
            if ($this->reminderAlreadySent($profile->id, NotificationType::CollabDayReminder, $collaboration->id)) {
                continue;
            }
            $this->createNotification(
                recipient: $profile,
                type: NotificationType::CollabDayReminder,
                title: $title,
                body: $body,
                targetId: $collaboration->id,
                targetType: 'collaboration',
            );
        }
    }

    /**
     * Send "Did your Kolab happen? Mark it complete." follow-up to both parties.
     * Idempotent: skips a party if a follow-up of this type was already sent.
     */
    public function notifyCollabFollowUpReminder(Collaboration $collaboration): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile']);

        $title = 'Did your Kolab happen?';
        $body = 'Mark it complete to earn your XP and keep your history up to date.';

        foreach ([$collaboration->creatorProfile, $collaboration->applicantProfile] as $profile) {
            if ($this->reminderAlreadySent($profile->id, NotificationType::CollabFollowUpReminder, $collaboration->id)) {
                continue;
            }
            $this->createNotification(
                recipient: $profile,
                type: NotificationType::CollabFollowUpReminder,
                title: $title,
                body: $body,
                targetId: $collaboration->id,
                targetType: 'collaboration',
            );
        }
    }

    /**
     * Check whether a reminder of the given type has already been sent
     * to this profile for this collaboration.
     */
    private function reminderAlreadySent(string $profileId, NotificationType $type, string $targetId): bool
    {
        return Notification::query()
            ->where('profile_id', $profileId)
            ->where('type', $type)
            ->where('target_id', $targetId)
            ->exists();
    }

    /**
     * Notify when a reward is won from spin-the-wheel.
     */
    public function notifyRewardWon(RewardClaim $claim): void
    {
        $claim->loadMissing(['eventReward', 'profile']);

        $this->createNotification(
            recipient: $claim->profile,
            type: NotificationType::RewardWon,
            title: 'You Won a Reward!',
            body: "You won \"{$claim->eventReward->name}\" from spin-the-wheel!",
            targetId: $claim->id,
            targetType: 'reward_claim',
        );
    }
}
