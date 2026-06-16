<?php

declare(strict_types=1);

namespace App\Enums;

enum JoinRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Declined = 'declined';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
