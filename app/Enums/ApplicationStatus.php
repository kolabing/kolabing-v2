<?php

declare(strict_types=1);

namespace App\Enums;

enum ApplicationStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
