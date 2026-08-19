<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Profile;
use Illuminate\Support\Str;

/**
 * Shareable public-profile URLs: `kolabing.com/p/barcelona-runners-1dd66a`.
 *
 * The readable half comes from the display name and the last six characters of the
 * UUID make it unique. `profiles.handle` would have been the natural key, but it is
 * only required during *attendee* onboarding — 5 of 94 production profiles have one
 * — so keying the public page on it would 404 for almost every business and
 * community. This needs no column, no backfill, and survives a rename: the suffix
 * still resolves, and the canonical tag points at the current slug.
 */
final class PublicProfileLink
{
    /** How many trailing UUID characters disambiguate a slug. */
    private const SUFFIX_LENGTH = 6;

    /** The path segment for a profile, e.g. `barcelona-runners-1dd66a`. */
    public static function slugFor(Profile $profile): string
    {
        $name = $profile->getExtendedProfile()?->name ?? $profile->name;
        $readable = Str::slug((string) $name);

        if ($readable === '') {
            $readable = 'profile';
        }

        return $readable.'-'.self::suffixOf($profile->id);
    }

    /** The absolute marketing URL for a profile. */
    public static function urlFor(Profile $profile): string
    {
        return rtrim((string) config('webapp.marketing_url'), '/').'/p/'.self::slugFor($profile);
    }

    /**
     * Resolve a slug back to a profile. Accepts the canonical `name-suffix` form, a
     * bare `@handle` (for the few profiles that set one), or a full UUID — so links
     * shared in any of those shapes keep working.
     */
    public static function resolve(string $slug): ?Profile
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $query = fn () => Profile::query()
            ->whereIn('user_type', ['business', 'community'])
            ->with(['businessProfile', 'communityProfile']);

        if (Str::isUuid($slug)) {
            return $query()->whereKey($slug)->first();
        }

        $suffix = Str::afterLast($slug, '-');

        if (self::looksLikeSuffix($suffix)) {
            // LIKE '%suffix' rather than right()/substr(): the two supported drivers
            // disagree on negative offsets, and this reads the same on both. The scan
            // is bounded by the profile count and the page itself is cacheable.
            $match = $query()->where('id', 'like', '%'.$suffix)->first();

            if ($match !== null) {
                return $match;
            }
        }

        return $query()->where('handle', ltrim($slug, '@'))->first();
    }

    private static function suffixOf(string $id): string
    {
        return substr(str_replace('-', '', $id), -self::SUFFIX_LENGTH);
    }

    private static function looksLikeSuffix(string $candidate): bool
    {
        return strlen($candidate) === self::SUFFIX_LENGTH
            && preg_match('/^[0-9a-f]+$/i', $candidate) === 1;
    }
}
