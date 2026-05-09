<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\Badge;
use App\Models\ChallengeCompletion;
use App\Models\ChatMessage;
use App\Models\Collaboration;
use App\Models\Notification;
use App\Models\Profile;
use App\Models\RewardClaim;
use App\Models\WithdrawalRequest;
use App\Services\Notifications\NotificationOrchestrator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class NotificationService
{
    public function __construct(
        private readonly NotificationOrchestrator $notificationOrchestrator
    ) {}

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
     * Create a notification record and dispatch a push notification if the recipient has a device token.
     */
    public function createNotification(
        Profile $recipient,
        NotificationType $type,
        string $title,
        string $body,
        ?Profile $actor = null,
        ?string $targetId = null,
        ?string $targetType = null,
        ?string $dedupeKey = null,
        ?string $deeplink = null,
        ?NotificationPriority $priority = null,
        array $data = [],
    ): ?Notification {
        if (! $this->isEnabled($type)) {
            return null;
        }

        return $this->notificationOrchestrator->send(
            recipient: $recipient,
            type: $type,
            title: $title,
            body: $body,
            actor: $actor,
            targetId: $targetId,
            targetType: $targetType,
            priority: $priority,
            dedupeKey: $dedupeKey,
            deeplink: $deeplink,
            data: $data,
        );
    }

    public function isEnabled(NotificationType $type): bool
    {
        return (bool) config("notifications.enabled_types.{$type->value}", true);
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

        $body = Str::limit($message->content, 120, '...');
        $actorName = $senderProfile->getExtendedProfile()?->name ?? 'Someone';

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::NewMessage,
            title: "{$actorName} sent a message",
            body: $body,
            actor: $senderProfile,
            targetId: $application->id,
            targetType: 'application',
            dedupeKey: "message:{$message->id}",
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

        $body = "{$actorName} applied to {$opportunityTitle}";

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationReceived,
            title: 'New application received',
            body: $body,
            actor: $actor,
            targetId: $application->id,
            targetType: 'application',
            dedupeKey: "application_received:{$application->id}",
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

        $body = "{$opportunityTitle} has been accepted";

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationAccepted,
            title: 'Application accepted',
            body: $body,
            actor: $actor,
            targetId: $application->id,
            targetType: 'application',
            dedupeKey: "application_accepted:{$application->id}",
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

        $body = "Your application for {$opportunityTitle} was declined";

        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationDeclined,
            title: 'Application update',
            body: $body,
            actor: $actor,
            targetId: $application->id,
            targetType: 'application',
            dedupeKey: "application_declined:{$application->id}",
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
            title: 'Challenge verified',
            body: 'Your challenge was approved',
            actor: $completion->verifier,
            targetId: $completion->id,
            targetType: 'challenge_completion',
            dedupeKey: "challenge_verified:{$completion->id}",
            deeplink: '/notifications',
        );
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
            title: 'You won a reward',
            body: $claim->eventReward->name,
            targetId: $claim->id,
            targetType: 'reward_claim',
            dedupeKey: "reward_won:{$claim->id}",
            deeplink: '/notifications',
        );
    }

    public function notifyBadgeAwarded(Profile $profile, Badge $badge): void
    {
        $this->notifyBadgeAwardedByName(
            profile: $profile,
            badgeName: $badge->name,
            targetId: $badge->id,
            targetType: 'badge',
            dedupeKey: "badge_awarded:{$profile->id}:{$badge->id}",
        );
    }

    public function notifyBadgeAwardedByName(
        Profile $profile,
        string $badgeName,
        string $targetId,
        string $targetType,
        string $dedupeKey,
    ): void {
        $this->createNotification(
            recipient: $profile,
            type: NotificationType::BadgeAwarded,
            title: 'New badge unlocked',
            body: $badgeName,
            targetId: $targetId,
            targetType: $targetType,
            dedupeKey: $dedupeKey,
            deeplink: '/notifications',
        );
    }

    public function notifyChallengeVerificationRequested(ChallengeCompletion $completion): void
    {
        $completion->loadMissing(['challenger', 'verifier']);

        $actorName = $completion->challenger->getExtendedProfile()?->name ?? 'Someone';

        $this->createNotification(
            recipient: $completion->verifier,
            type: NotificationType::ChallengeVerificationRequested,
            title: 'Challenge verification needed',
            body: "{$actorName} needs your verification",
            actor: $completion->challenger,
            targetId: $completion->id,
            targetType: 'challenge_completion',
            dedupeKey: "challenge_verification_requested:{$completion->id}",
            deeplink: '/notifications',
        );
    }

    public function notifyChallengeRejected(ChallengeCompletion $completion): void
    {
        $completion->loadMissing(['challenger', 'verifier']);

        $this->createNotification(
            recipient: $completion->challenger,
            type: NotificationType::ChallengeRejected,
            title: 'Challenge update',
            body: 'Your challenge was rejected',
            actor: $completion->verifier,
            targetId: $completion->id,
            targetType: 'challenge_completion',
            dedupeKey: "challenge_rejected:{$completion->id}",
            deeplink: '/notifications',
        );
    }

    public function notifyCollaborationScheduled(Collaboration $collaboration, ?Profile $actor = null): void
    {
        $collaboration->loadMissing([
            'collabOpportunity',
            'creatorProfile.businessProfile',
            'creatorProfile.communityProfile',
            'applicantProfile.businessProfile',
            'applicantProfile.communityProfile',
        ]);

        foreach ($this->collaborationRecipients($collaboration) as $recipient) {
            $partner = $recipient->is($collaboration->creatorProfile)
                ? $collaboration->applicantProfile
                : $collaboration->creatorProfile;
            $partnerName = $partner->getExtendedProfile()?->name ?? 'Your partner';
            $scheduledDate = $collaboration->scheduled_date?->format('Y-m-d') ?? 'soon';

            $this->createNotification(
                recipient: $recipient,
                type: NotificationType::CollaborationScheduled,
                title: 'Collaboration scheduled',
                body: "{$partnerName} confirmed {$scheduledDate}",
                actor: $actor,
                targetId: $collaboration->id,
                targetType: 'collaboration',
                dedupeKey: "collaboration_scheduled:{$collaboration->id}:{$recipient->id}",
            );
        }
    }

    public function notifyCollaborationRescheduled(Collaboration $collaboration, ?Profile $actor = null): void
    {
        $collaboration->loadMissing([
            'creatorProfile.businessProfile',
            'creatorProfile.communityProfile',
            'applicantProfile.businessProfile',
            'applicantProfile.communityProfile',
        ]);

        $scheduledDate = $collaboration->scheduled_date?->format('Y-m-d') ?? 'soon';

        foreach ($this->collaborationRecipients($collaboration) as $recipient) {
            $this->createNotification(
                recipient: $recipient,
                type: NotificationType::CollaborationRescheduled,
                title: 'Collaboration updated',
                body: "New date: {$scheduledDate}",
                actor: $actor,
                targetId: $collaboration->id,
                targetType: 'collaboration',
                dedupeKey: "collaboration_rescheduled:{$collaboration->id}:{$scheduledDate}:{$recipient->id}",
            );
        }
    }

    public function notifyCollaborationCancelled(
        Collaboration $collaboration,
        ?Profile $actor = null,
        ?string $reason = null,
    ): void {
        $collaboration->loadMissing([
            'creatorProfile.businessProfile',
            'creatorProfile.communityProfile',
            'applicantProfile.businessProfile',
            'applicantProfile.communityProfile',
        ]);

        $actorName = $actor?->getExtendedProfile()?->name ?? 'A participant';
        $body = "{$actorName} cancelled this collaboration";

        if ($reason !== null && $reason !== '') {
            $body .= ": {$reason}";
        }

        foreach ($this->collaborationRecipients($collaboration) as $recipient) {
            $this->createNotification(
                recipient: $recipient,
                type: NotificationType::CollaborationCancelled,
                title: 'Collaboration cancelled',
                body: $body,
                actor: $actor,
                targetId: $collaboration->id,
                targetType: 'collaboration',
                dedupeKey: "collaboration_cancelled:{$collaboration->id}:{$recipient->id}",
            );
        }
    }

    public function notifyCollaborationReminder24h(Collaboration $collaboration): void
    {
        $this->notifyCollaborationReminder($collaboration, NotificationType::CollaborationReminder24h);
    }

    public function notifyCollaborationReminderSameDay(Collaboration $collaboration): void
    {
        $this->notifyCollaborationReminder($collaboration, NotificationType::CollaborationReminderSameDay);
    }

    public function notifyWithdrawalApproved(WithdrawalRequest $withdrawalRequest): void
    {
        $withdrawalRequest->loadMissing('profile');

        $this->createNotification(
            recipient: $withdrawalRequest->profile,
            type: NotificationType::WithdrawalApproved,
            title: 'Withdrawal approved',
            body: 'Your withdrawal is approved and queued for payout',
            targetId: $withdrawalRequest->id,
            targetType: 'withdrawal_request',
            dedupeKey: "withdrawal_approved:{$withdrawalRequest->id}",
        );
    }

    public function notifyWithdrawalRejected(WithdrawalRequest $withdrawalRequest, ?string $reason = null): void
    {
        $withdrawalRequest->loadMissing('profile');

        $this->createNotification(
            recipient: $withdrawalRequest->profile,
            type: NotificationType::WithdrawalRejected,
            title: 'Withdrawal rejected',
            body: $reason ?: 'Your withdrawal could not be approved.',
            targetId: $withdrawalRequest->id,
            targetType: 'withdrawal_request',
            dedupeKey: "withdrawal_rejected:{$withdrawalRequest->id}",
        );
    }

    public function notifyWithdrawalPaid(WithdrawalRequest $withdrawalRequest): void
    {
        $withdrawalRequest->loadMissing('profile');

        $this->createNotification(
            recipient: $withdrawalRequest->profile,
            type: NotificationType::WithdrawalPaid,
            title: 'Withdrawal paid',
            body: 'Your payout has been completed',
            targetId: $withdrawalRequest->id,
            targetType: 'withdrawal_request',
            dedupeKey: "withdrawal_paid:{$withdrawalRequest->id}",
        );
    }

    public function notifyReferralRewardEarned(
        Profile $recipient,
        string $code,
        ?Profile $actor = null,
        ?string $referenceId = null,
    ): void {
        $this->createNotification(
            recipient: $recipient,
            type: NotificationType::ReferralRewardEarned,
            title: 'Referral reward earned',
            body: "You earned a reward from referral code {$code}",
            actor: $actor,
            targetId: $recipient->id,
            targetType: 'profile',
            dedupeKey: "referral_reward_earned:{$recipient->id}:".($referenceId ?? $code),
        );
    }

    /**
     * @return array<int, Profile>
     */
    private function collaborationRecipients(Collaboration $collaboration): array
    {
        return [
            $collaboration->creatorProfile,
            $collaboration->applicantProfile,
        ];
    }

    private function notifyCollaborationReminder(Collaboration $collaboration, NotificationType $type): void
    {
        $collaboration->loadMissing(['collabOpportunity', 'creatorProfile', 'applicantProfile']);

        $title = $type === NotificationType::CollaborationReminder24h
            ? 'Reminder: collaboration tomorrow'
            : 'Reminder: collaboration today';
        $body = $type === NotificationType::CollaborationReminder24h
            ? sprintf(
                '%s starts on %s',
                $collaboration->collabOpportunity->title,
                $collaboration->scheduled_date?->format('Y-m-d') ?? 'soon'
            )
            : sprintf(
                '%s starts at %s',
                $collaboration->collabOpportunity->title,
                $collaboration->scheduled_date?->format('Y-m-d') ?? 'today'
            );

        foreach ($this->collaborationRecipients($collaboration) as $recipient) {
            $suffix = $type === NotificationType::CollaborationReminder24h
                ? $collaboration->scheduled_date?->format('Y-m-d') ?? now()->toDateString()
                : $collaboration->scheduled_date?->format('Y-m-d') ?? now()->toDateString();

            $this->createNotification(
                recipient: $recipient,
                type: $type,
                title: $title,
                body: $body,
                targetId: $collaboration->id,
                targetType: 'collaboration',
                dedupeKey: "{$type->value}:{$collaboration->id}:{$suffix}:{$recipient->id}",
            );
        }
    }
}
