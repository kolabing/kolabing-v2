<?php

declare(strict_types=1);

namespace App\Enums;

enum JoinPolicy: string
{
    case Open = 'open';
    case InviteOnly = 'invite_only';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
