<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeCategory: string
{
    // Legacy peer-verified event categories.
    case IceBreaker = 'ice_breaker';
    case CulturalExchange = 'cultural_exchange';
    case BarcelonaVibe = 'barcelona_vibe';
    case CreativeFun = 'creative_fun';

    // Mission categories (self-tracked onboarding/growth missions).
    case Onboarding = 'onboarding';
    case Attendance = 'attendance';
    case Engagement = 'engagement';
    case Content = 'content';
    case Referral = 'referral';
    case Growth = 'growth';
    case Social = 'social';
    case Milestone = 'milestone';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
