<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserType;
use App\Models\Kolab;
use Illuminate\Support\Str;

/**
 * Who posted a Kolab, as the open web is allowed to describe them.
 *
 * The asymmetry here is not a styling choice, it mirrors the in-app rule exactly:
 *
 *  - A **business** name is never blurred from anyone. ROLES §3.3: "The business name
 *    (never blurred; communities have full access)". Naming it publicly reveals nothing
 *    that an account would not immediately reveal.
 *  - A **community** name is blurred from a free (non-subscribed) business on Explore
 *    (§2.5) and revealed by subscribing. If this page printed the community's name next
 *    to its Kolab, a business could read the pairing by simply logging out — the blur
 *    would still be in the app and worth nothing. So the community is described by type
 *    and city ("A run club in Barcelona"), never named.
 *
 * Note what is *not* being protected: the community's identity itself is already public
 * at `kolabing.com/p/{slug}` (§4.2). What §2.5 protects is the *pairing* — which
 * community is asking for what — and that is exactly what stays behind sign-up here.
 *
 * @phpstan-type PosterDescription array{name: string|null, description: string, is_named: bool}
 */
final class PublicKolabPoster
{
    /**
     * @return PosterDescription
     */
    public static function describe(Kolab $kolab): array
    {
        $profile = $kolab->creatorProfile;

        if ($profile?->user_type === UserType::Business) {
            $name = trim((string) ($profile->businessProfile?->name ?? $profile->name ?? ''));

            if ($name !== '') {
                return ['name' => $name, 'description' => $name, 'is_named' => true];
            }

            return ['name' => null, 'description' => self::unnamed('business', $kolab), 'is_named' => false];
        }

        return ['name' => null, 'description' => self::unnamed('community', $kolab), 'is_named' => false];
    }

    /**
     * "A run club in Barcelona" — type and place, no name.
     */
    private static function unnamed(string $role, Kolab $kolab): string
    {
        $profile = $kolab->creatorProfile;

        $type = $role === 'community'
            ? $profile?->communityProfile?->community_type
            : $profile?->businessProfile?->business_type;

        $label = is_string($type) && trim($type) !== ''
            // Slugs are stored hyphenated or underscored depending on when the row was
            // written; headline handles both and never prints "Run_Club" (§3.4).
            ? Str::lower(Str::headline($type))
            : ($role === 'community' ? 'community' : 'business');

        $city = trim((string) $kolab->preferred_city);

        // "Unknown" is a real value in this column, written by an older client. Printing
        // "A run club in Unknown" would be worse than printing no place at all.
        $hasCity = $city !== '' && Str::lower($city) !== 'unknown';

        return $hasCity
            ? Str::ucfirst('a '.$label.' in '.$city)
            : Str::ucfirst('a local '.$label);
    }
}
