<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunityPointSource: string
{
    case EventCheckIn = 'event_check_in';
    case GoalCompleted = 'goal_completed';
    case ChallengeVerified = 'challenge_verified';
    case Redemption = 'redemption';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
