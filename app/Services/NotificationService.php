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
            'kolab.creatorProfile',
            'applicantProfile',
        ]);

        $opportunity = $this->applicationOpportunity($application);
        if ($opportunity === null) {
            return;
        }

        $senderProfileId = $message->sender_profile_id;

        // Determine recipient: if sender is applicant, notify creator; otherwise notify applicant
        $recipient = $senderProfileId === $application->applicant_profile_id
            ? $opportunity->creatorProfile
            : $application->applicantProfile;

        $senderProfile = $senderProfileId === $application->applicant_profile_id
            ? $application->applicantProfile
            : $opportunity->creatorProfile;

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
            'kolab.creatorProfile',
            'applicantProfile.businessProfile',
            'applicantProfile.communityProfile',
        ]);

        $opportunity = $this->applicationOpportunity($application);
        if ($opportunity === null) {
            return;
        }

        $recipient = $opportunity->creatorProfile;
        $actor = $application->applicantProfile;
        $actorName = $actor->getExtendedProfile()?->name ?? 'Someone';
        $opportunityTitle = $opportunity->title;

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
            'kolab.creatorProfile',
            'applicantProfile',
        ]);

        $opportunity = $this->applicationOpportunity($application);
        if ($opportunity === null) {
            return;
        }

        $recipient = $application->applicantProfile;
        $actor = $opportunity->creatorProfile;
        $opportunityTitle = $opportunity->title;

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
            'kolab.creatorProfile',
            'applicantProfile',
        ]);

        $opportunity = $this->applicationOpportunity($application);
        if ($opportunity === null) {
            return;
        }

        $recipient = $application->applicantProfile;
        $actor = $opportunity->creatorProfile;
        $opportunityTitle = $opportunity->title;

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
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);

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
     * Notify when a collaboration is created from an accepted application.
     * System event — no actor; both parties get the same copy.
     */
    public function notifyCollaborationCreated(Collaboration $collaboration): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationCreated,
            actor: null,
            title: 'Collaboration started',
            sharedBody: "Your collaboration for \"{$kolab}\" is set up. Tap to view the details.",
        );
    }

    /**
     * Notify when a collaboration is activated. The actor (who activated it)
     * sees actor-aware copy; the counterpart sees "{name} marked…".
     */
    public function notifyCollaborationActivated(Collaboration $collaboration, Profile $actor): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);
        $name = $this->actorDisplayName($actor);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationActivated,
            actor: $actor,
            title: 'Collaboration activated',
            actorBody: "You marked the collaboration for \"{$kolab}\" as active.",
            counterpartBody: "{$name} marked your collaboration for \"{$kolab}\" as active.",
        );
    }

    /**
     * Notify when one party submits feedback. The reviewer (actor) sees
     * "Feedback submitted"; the counterpart sees "New feedback".
     */
    public function notifyCollaborationFeedbackReceived(Collaboration $collaboration, Profile $actor): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);
        $name = $this->actorDisplayName($actor);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationFeedbackReceived,
            actor: $actor,
            actorTitle: 'Feedback submitted',
            actorBody: "Your feedback for \"{$kolab}\" has been recorded.",
            counterpartTitle: 'New feedback',
            counterpartBody: "{$name} left feedback for your collaboration \"{$kolab}\".",
        );
    }

    /**
     * Notify when a collaboration is completed. With an actor (manual / admin
     * force) both parties get "Collaboration completed" with actor-aware copy;
     * with a null actor (auto-complete) both get the shared auto copy.
     */
    public function notifyCollaborationCompleted(Collaboration $collaboration, ?Profile $actor): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);

        if ($actor === null) {
            $this->notifyBothParties(
                collaboration: $collaboration,
                type: NotificationType::CollaborationCompleted,
                actor: null,
                title: 'Collaboration completed',
                sharedBody: "Your collaboration for \"{$kolab}\" was automatically marked complete.",
            );

            return;
        }

        $name = $this->actorDisplayName($actor);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationCompleted,
            actor: $actor,
            title: 'Collaboration completed',
            actorBody: "You marked the collaboration for \"{$kolab}\" as complete.",
            counterpartBody: "{$name} marked your collaboration for \"{$kolab}\" as complete.",
        );
    }

    /**
     * Notify when a collaboration is cancelled. The actor sees "You cancelled…";
     * the counterpart sees "{name} cancelled…". When the actor is null (e.g. a
     * maintainer cancel without a profile) both parties get the counterpart copy
     * with a "Someone" fallback name.
     */
    public function notifyCollaborationCancelled(Collaboration $collaboration, ?Profile $actor): void
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile', 'kolab']);
        $kolab = $this->collaborationKolabTitle($collaboration);
        $name = $this->actorDisplayName($actor);

        $this->notifyBothParties(
            collaboration: $collaboration,
            type: NotificationType::CollaborationCancelled,
            actor: $actor,
            title: 'Collaboration cancelled',
            actorBody: "You cancelled the collaboration for \"{$kolab}\".",
            counterpartBody: "{$name} cancelled your collaboration for \"{$kolab}\".",
        );
    }

    /**
     * Notify both parties of a collaboration. The actor (if a participant)
     * receives actor-aware copy; everyone else receives the counterpart copy.
     * Pass either a single $sharedBody (system / no-actor events, optionally
     * with a single $title) or distinct $actorBody / $counterpartBody (and
     * optional per-side titles). Each side defaults to $title when a
     * side-specific title is not given.
     */
    private function notifyBothParties(
        Collaboration $collaboration,
        NotificationType $type,
        ?Profile $actor,
        ?string $title = null,
        ?string $sharedBody = null,
        ?string $actorTitle = null,
        ?string $actorBody = null,
        ?string $counterpartTitle = null,
        ?string $counterpartBody = null,
    ): void {
        foreach ([$collaboration->creatorProfile, $collaboration->applicantProfile] as $profile) {
            if ($profile === null) {
                continue;
            }

            $isActor = $actor !== null && $profile->id === $actor->id;

            $resolvedTitle = $isActor
                ? ($actorTitle ?? $title)
                : ($counterpartTitle ?? $title);

            $resolvedBody = $sharedBody
                ?? ($isActor ? $actorBody : $counterpartBody)
                ?? $counterpartBody
                ?? $actorBody;

            $this->createNotification(
                recipient: $profile,
                type: $type,
                title: (string) $resolvedTitle,
                body: (string) $resolvedBody,
                actor: $actor,
                targetId: $collaboration->id,
                targetType: 'collaboration',
            );
        }
    }

    /**
     * The collaboration's kolab title, with a safe fallback.
     */
    private function collaborationKolabTitle(Collaboration $collaboration): string
    {
        return $collaboration->kolab?->title ?? 'your Kolab';
    }

    /**
     * The actor's display name, mirroring the notifyApplicationReceived
     * convention (extended-profile name, "Someone" fallback).
     */
    private function actorDisplayName(?Profile $actor): string
    {
        return $actor?->getExtendedProfile()?->name ?? 'Someone';
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

    private function applicationOpportunity(Application $application): mixed
    {
        return $application->kolab;
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
