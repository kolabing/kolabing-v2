<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\Notification;
use App\Models\Profile;
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
     * Create a notification record.
     */
    public function createNotification(
        Profile $recipient,
        NotificationType $type,
        string $title,
        string $body,
        ?Profile $actor = null,
        ?string $targetId = null,
        ?string $targetType = null,
    ): Notification {
        return Notification::create([
            'profile_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'actor_profile_id' => $actor?->id,
            'target_id' => $targetId,
            'target_type' => $targetType,
        ]);
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
}
