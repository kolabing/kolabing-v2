<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Kolab;
use App\Services\PublicKolabFeedService;
use Illuminate\Support\Str;

/**
 * Shareable public URLs for Kolabs: `kolabing.com/kolabs/sunday-run-brunch-1dd66a`.
 *
 * Same convention as {@see PublicEventLink} and {@see PublicProfileLink} — a readable
 * title plus the last six characters of the UUID — so a link survives a retitle and a
 * full UUID still resolves. Kept as its own class for the same reason those two are
 * separate: the visibility rules differ per table, and the six-character convention is
 * the only thing the three share.
 */
final class PublicKolabLink
{
    private const SUFFIX_LENGTH = 6;

    public static function slugFor(Kolab $kolab): string
    {
        $readable = Str::slug((string) $kolab->title);

        if ($readable === '') {
            $readable = 'kolab';
        }

        return $readable.'-'.substr(str_replace('-', '', $kolab->id), -self::SUFFIX_LENGTH);
    }

    public static function urlFor(Kolab $kolab): string
    {
        return url('/kolabs/'.self::slugFor($kolab));
    }

    /**
     * Resolve a slug back to a Kolab, applying the same gate the listing applies.
     *
     * A draft or date-exhausted Kolab must not become readable by guessing a URL, and
     * it must not become readable by *keeping* one either — a Kolab that has since
     * closed stops resolving here. The gate lives in
     * {@see \App\Services\PublicKolabFeedService::publishable()} so the list, the
     * detail page and the sitemap can never drift apart.
     */
    public static function resolve(string $slug): ?Kolab
    {
        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        $query = fn () => app(PublicKolabFeedService::class)
            ->publishable()
            ->with(['creatorProfile.businessProfile', 'creatorProfile.communityProfile']);

        if (Str::isUuid($slug)) {
            return $query()->whereKey($slug)->first();
        }

        $suffix = Str::afterLast($slug, '-');

        if (strlen($suffix) === self::SUFFIX_LENGTH && preg_match('/^[0-9a-f]+$/i', $suffix) === 1) {
            // LIKE '%suffix' rather than right()/substr(): the two drivers disagree
            // on negative offsets. See PublicProfileLink for the same reasoning.
            return $query()->where('id', 'like', '%'.$suffix)->first();
        }

        return null;
    }
}
