<?php

declare(strict_types=1);

namespace App\Enums;

enum CollaborationStatus: string
{
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
