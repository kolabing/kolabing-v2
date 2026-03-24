<?php

declare(strict_types=1);

namespace App\Enums;

enum KolabStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
