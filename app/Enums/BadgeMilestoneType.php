<?php

declare(strict_types=1);

namespace App\Enums;

enum BadgeMilestoneType: string
{
    case FirstCheckin = 'first_checkin';
    case FirstChallenge = 'first_challenge';
    case SocialButterfly = 'social_butterfly_10';
    case ChallengeMaster = 'challenges_completed_50';
    case EventGuru = 'events_attended_10';
    case PointHunter = 'points_500';
    case Legend = 'points_2000';
    case RewardCollector = 'rewards_won_10';
    case LoyalAttendee = 'events_attended_5';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
