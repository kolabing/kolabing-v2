<?php

declare(strict_types=1);

namespace App\Enums;

enum MultiKolabCompensationType: string
{
    case Paid = 'paid';
    case SponsoredInKind = 'sponsored_in_kind';
    case ValueExchange = 'value_exchange';
    case Negotiable = 'negotiable';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
