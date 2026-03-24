<?php

declare(strict_types=1);

namespace App\Enums;

enum IntentType: string
{
    case CommunitySeeking = 'community_seeking';
    case VenuePromotion = 'venue_promotion';
    case ProductPromotion = 'product_promotion';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
