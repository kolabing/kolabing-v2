<?php

declare(strict_types=1);

namespace App\Enums;

enum MultiKolabEligibleAccountType: string
{
    case Business = 'business';
    case Community = 'community';
    case Either = 'either';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
