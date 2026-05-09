<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationPriority: string
{
    case High = 'high';
    case Normal = 'normal';
    case Low = 'low';

    public static function forType(NotificationType $type): self
    {
        return match ($type) {
            NotificationType::NewMessage,
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::CollaborationScheduled,
            NotificationType::CollaborationRescheduled,
            NotificationType::CollaborationCancelled,
            NotificationType::ChallengeVerificationRequested,
            NotificationType::ChallengeVerified,
            NotificationType::WithdrawalRejected,
            NotificationType::RewardWon => self::High,
            NotificationType::PendingApplicationNudge,
            NotificationType::OpportunityMatch,
            NotificationType::NearbyEventMatch,
            NotificationType::WalletThresholdReached,
            NotificationType::DormantUserReactivation => self::Low,
            default => self::Normal,
        };
    }
}
