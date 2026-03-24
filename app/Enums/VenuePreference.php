<?php

declare(strict_types=1);

namespace App\Enums;

enum VenuePreference: string
{
    case BusinessProvides = 'business_provides';
    case CommunityProvides = 'community_provides';
    case NoVenue = 'no_venue';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
