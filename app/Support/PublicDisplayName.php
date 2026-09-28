<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Profile;
use Illuminate\Support\Str;

/**
 * The name shown for a person on any ranked / shared surface (leaderboards).
 * profiles.name, else the part of the email before "@". Never the email.
 */
final class PublicDisplayName
{
    public static function for(?Profile $profile): string
    {
        if ($profile === null) {
            return 'Member';
        }

        $name = trim((string) $profile->name);
        if ($name !== '') {
            return $name;
        }

        $prefix = Str::before((string) $profile->email, '@');

        return $prefix !== '' ? $prefix : 'Member';
    }

    /**
     * An organiser on the city league: the community's name first.
     */
    public static function forOrganiser(?Profile $profile): string
    {
        $communityName = trim((string) $profile?->communityProfile?->name);

        return $communityName !== '' ? $communityName : self::for($profile);
    }
}
