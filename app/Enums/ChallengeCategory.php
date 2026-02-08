<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeCategory: string
{
    case IceBreaker = 'ice_breaker';
    case CulturalExchange = 'cultural_exchange';
    case BarcelonaVibe = 'barcelona_vibe';
    case CreativeFun = 'creative_fun';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
