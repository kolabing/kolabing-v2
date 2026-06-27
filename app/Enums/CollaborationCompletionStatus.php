<?php

declare(strict_types=1);

namespace App\Enums;

enum CollaborationCompletionStatus: string
{
    case Yes = 'yes';
    case No = 'no';
    case NotYet = 'not_yet';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
