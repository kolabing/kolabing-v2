<?php

declare(strict_types=1);

use App\Enums\NotificationType;

return [
    'push_delivery_enabled' => filter_var(
        env('NOTIFICATIONS_PUSH_ENABLED', true),
        FILTER_VALIDATE_BOOL,
        FILTER_NULL_ON_FAILURE
    ) ?? true,
    'enabled_types' => [
        NotificationType::NewMessage->value => true,
        NotificationType::ApplicationReceived->value => true,
        NotificationType::ApplicationAccepted->value => true,
        NotificationType::ApplicationDeclined->value => true,
        NotificationType::BadgeAwarded->value => true,
        NotificationType::ChallengeVerified->value => true,
        NotificationType::RewardWon->value => true,

        NotificationType::CollaborationScheduled->value => false,
        NotificationType::CollaborationRescheduled->value => false,
        NotificationType::CollaborationCancelled->value => false,
        NotificationType::CollaborationReminder24h->value => false,
        NotificationType::CollaborationReminderSameDay->value => false,
        NotificationType::ChallengeVerificationRequested->value => false,
        NotificationType::ChallengeRejected->value => false,
        NotificationType::WithdrawalApproved->value => false,
        NotificationType::WithdrawalRejected->value => false,
        NotificationType::WithdrawalPaid->value => false,
        NotificationType::ReferralRewardEarned->value => false,
        NotificationType::PendingApplicationNudge->value => false,
        NotificationType::OpportunityMatch->value => false,
        NotificationType::NearbyEventMatch->value => false,
        NotificationType::WalletThresholdReached->value => false,
        NotificationType::DormantUserReactivation->value => false,
    ],
    'growth_limits' => [
        'per_24_hours' => 1,
        'per_7_days' => 3,
    ],
    'growth' => [
        'nearby_radius_km' => (float) env('NOTIFICATIONS_GROWTH_NEARBY_RADIUS_KM', 25),
        'location_consent_days' => (int) env('NOTIFICATIONS_GROWTH_LOCATION_CONSENT_DAYS', 30),
        'dormant_days' => [7, 14],
    ],
];
