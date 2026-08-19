<?php

declare(strict_types=1);

namespace App\Enums;

enum MultiKolabEventStatus: string
{
    case Draft = 'draft';
    case Recruiting = 'recruiting';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
