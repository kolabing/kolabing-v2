<?php

declare(strict_types=1);

namespace App\Enums;

enum FriendshipStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Blocked = 'blocked';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
