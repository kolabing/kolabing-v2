<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case NewMessage = 'new_message';
    case ApplicationReceived = 'application_received';
    case ApplicationAccepted = 'application_accepted';
    case ApplicationDeclined = 'application_declined';
    case BadgeAwarded = 'badge_awarded';
    case ChallengeVerified = 'challenge_verified';
    case RewardWon = 'reward_won';
    case PointsEarned = 'points_earned';
    case GamificationBadgeEarned = 'gamification_badge_earned';
    case WithdrawalProcessed = 'withdrawal_processed';
    case KolabCreateIncomplete = 'kolab_create_incomplete';
    case ApplicationPending = 'application_pending';
    case UnreadMessage = 'unread_message';
    case CollabDayReminder = 'collab_day_reminder';
    case CollabFollowUpReminder = 'collab_followup_reminder';
    case WaitlistPromoted = 'waitlist_promoted';
    case EventCancelled = 'event_cancelled';
    case CommunityJoinRequested = 'community_join_requested';
    case CommunityJoinApproved = 'community_join_approved';
    case CommunityJoinDeclined = 'community_join_declined';
    case CommunityVerified = 'community_verified';
    case CommunityVerificationRejected = 'community_verification_rejected';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
