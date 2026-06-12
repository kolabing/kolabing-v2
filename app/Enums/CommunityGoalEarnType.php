<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunityGoalEarnType: string
{
    case EventCheckIns = 'event_check_ins';
    case Challenge = 'challenge';
    case DaysInCommunity = 'days_in_community';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
