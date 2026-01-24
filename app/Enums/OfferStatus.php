<?php

declare(strict_types=1);

namespace App\Enums;

enum OfferStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Completed = 'completed';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
