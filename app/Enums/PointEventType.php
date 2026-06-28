<?php

declare(strict_types=1);

namespace App\Enums;

enum PointEventType: string
{
    case CollaborationComplete = 'collaboration_complete';
    // Awarded once per party for the lightweight yes/no/not_yet completion
    // confirmation step — distinct from CollaborationComplete (rich feedback)
    // so the two never collide/double-pay (2026-06-26 completion-flow PR 1).
    case CollaborationCompletionConfirmed = 'collaboration_completion_confirmed';
    case KolabPublished = 'kolab_published';
    case FirstKolabBonus = 'first_kolab_bonus';
    case ReviewPosted = 'review_posted';
    case UgcPosted = 'ugc_posted';
    case ReferralConversion = 'referral_conversion';
    case MissionCompleted = 'mission_completed';
    case Withdrawal = 'withdrawal';
    // Community-points earns. Earning a community's points also awards global XP
    // through these rules (XP stays global, points stay per-community).
    case EventCheckin = 'event_checkin';
    case CommunityGoalCompleted = 'community_goal_completed';
    case CommunityChallengeVerified = 'community_challenge_verified';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the default points for this event type.
     */
    public function defaultPoints(): int
    {
        return match ($this) {
            self::CollaborationComplete => 10,
            self::CollaborationCompletionConfirmed => 10,
            self::KolabPublished => 30,
            self::FirstKolabBonus => 50,
            self::ReviewPosted => 10,
            self::UgcPosted => 10,
            self::ReferralConversion => 50,
            // Mission awards carry their own per-mission `points`; this default
            // is only a fallback if MissionCompleted ever flows through the
            // xp_earn_rules lookup (it should not — MissionService credits the
            // ledger directly with the mission's points).
            self::MissionCompleted => 0,
            self::Withdrawal => 0,
            self::EventCheckin => 10,
            self::CommunityGoalCompleted => 25,
            self::CommunityChallengeVerified => 15,
        };
    }
}
