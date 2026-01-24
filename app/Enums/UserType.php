<?php

declare(strict_types=1);

namespace App\Enums;

enum UserType: string
{
    case Business = 'business';
    case Community = 'community';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
