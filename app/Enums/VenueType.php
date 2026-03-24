<?php

declare(strict_types=1);

namespace App\Enums;

enum VenueType: string
{
    case Restaurant = 'restaurant';
    case Cafe = 'cafe';
    case BarLounge = 'bar_lounge';
    case Hotel = 'hotel';
    case Coworking = 'coworking';
    case SportsFacility = 'sports_facility';
    case EventSpace = 'event_space';
    case Rooftop = 'rooftop';
    case BeachClub = 'beach_club';
    case RetailStore = 'retail_store';
    case Other = 'other';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
