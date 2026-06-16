<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunityBadgeCriteriaType: string
{
    case PointsThreshold = 'points_threshold';
    case EventCheckIns = 'event_check_ins';
    case DaysInCommunity = 'days_in_community';
    case ChallengesCompleted = 'challenges_completed';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
