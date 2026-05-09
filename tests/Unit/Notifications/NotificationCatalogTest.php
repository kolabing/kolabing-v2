<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\NotificationType;
use Tests\TestCase;

class NotificationCatalogTest extends TestCase
{
    public function test_notification_type_enum_matches_the_planned_catalog(): void
    {
        $this->assertSame([
            'new_message',
            'application_received',
            'application_accepted',
            'application_declined',
            'collaboration_scheduled',
            'collaboration_rescheduled',
            'collaboration_cancelled',
            'collaboration_reminder_24h',
            'collaboration_reminder_same_day',
            'challenge_verification_requested',
            'challenge_verified',
            'challenge_rejected',
            'reward_won',
            'badge_awarded',
            'withdrawal_approved',
            'withdrawal_rejected',
            'withdrawal_paid',
            'referral_reward_earned',
            'pending_application_nudge',
            'opportunity_match',
            'nearby_event_match',
            'wallet_threshold_reached',
            'dormant_user_reactivation',
        ], NotificationType::values());
    }
}
