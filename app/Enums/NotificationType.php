<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case NewMessage = 'new_message';
    case ApplicationReceived = 'application_received';
    case ApplicationAccepted = 'application_accepted';
    case ApplicationDeclined = 'application_declined';
    case CollaborationScheduled = 'collaboration_scheduled';
    case CollaborationRescheduled = 'collaboration_rescheduled';
    case CollaborationCancelled = 'collaboration_cancelled';
    case CollaborationReminder24h = 'collaboration_reminder_24h';
    case CollaborationReminderSameDay = 'collaboration_reminder_same_day';
    case ChallengeVerificationRequested = 'challenge_verification_requested';
    case ChallengeVerified = 'challenge_verified';
    case ChallengeRejected = 'challenge_rejected';
    case RewardWon = 'reward_won';
    case BadgeAwarded = 'badge_awarded';
    case WithdrawalApproved = 'withdrawal_approved';
    case WithdrawalRejected = 'withdrawal_rejected';
    case WithdrawalPaid = 'withdrawal_paid';
    case ReferralRewardEarned = 'referral_reward_earned';
    case PendingApplicationNudge = 'pending_application_nudge';
    case OpportunityMatch = 'opportunity_match';
    case NearbyEventMatch = 'nearby_event_match';
    case WalletThresholdReached = 'wallet_threshold_reached';
    case DormantUserReactivation = 'dormant_user_reactivation';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function isGrowth(): bool
    {
        return in_array($this, [
            self::PendingApplicationNudge,
            self::OpportunityMatch,
            self::NearbyEventMatch,
            self::WalletThresholdReached,
            self::DormantUserReactivation,
        ], true);
    }

    public function ignoresQuietHours(): bool
    {
        return in_array($this, [
            self::NewMessage,
            self::ApplicationReceived,
            self::ApplicationAccepted,
            self::ApplicationDeclined,
            self::CollaborationScheduled,
            self::CollaborationRescheduled,
            self::CollaborationCancelled,
            self::CollaborationReminder24h,
            self::CollaborationReminderSameDay,
            self::ChallengeVerificationRequested,
        ], true);
    }

    public function preferenceField(): string
    {
        return match ($this) {
            self::NewMessage => 'messages_enabled',
            self::ApplicationReceived,
            self::ApplicationAccepted,
            self::ApplicationDeclined => 'applications_enabled',
            self::CollaborationScheduled,
            self::CollaborationRescheduled,
            self::CollaborationCancelled,
            self::CollaborationReminder24h,
            self::CollaborationReminderSameDay => 'collaborations_enabled',
            self::PendingApplicationNudge,
            self::OpportunityMatch,
            self::NearbyEventMatch,
            self::WalletThresholdReached,
            self::DormantUserReactivation => 'marketing_enabled',
            default => 'rewards_enabled',
        };
    }
}
