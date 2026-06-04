<?php

declare(strict_types=1);

namespace App\Enums;

enum CommunityType: string
{
    case Greek = 'greek';
    case Fitness = 'fitness';
    case Running = 'running';
    case Business = 'business';
    case Other = 'other';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
