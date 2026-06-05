<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatParticipantState: string
{
    case Joined = 'joined';
    case Banned = 'banned';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
