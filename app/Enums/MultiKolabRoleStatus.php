<?php

declare(strict_types=1);

namespace App\Enums;

enum MultiKolabRoleStatus: string
{
    case Open = 'open';
    case Filled = 'filled';
    case Closed = 'closed';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
