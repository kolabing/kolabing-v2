<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which side of a proposed pair a generated suggestion is addressed to.
 * Stored on `kolab_suggestions.audience`, and always mirrors the
 * `user_type` of the row's `viewer_profile_id`: a business is shown
 * communities to collaborate with, and a community is shown businesses.
 * Attendees are never an audience for suggestions.
 */
enum SuggestionAudience: string
{
    case Business = 'business';
    case Community = 'community';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
