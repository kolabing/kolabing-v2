<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\City;
use App\Models\Kolab;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Which Kolabs the open web is allowed to show, in one place.
 *
 * Three surfaces read this — the homepage strip, `kolabing.com/kolabs`, and
 * `sitemap.xml` — and a fourth resolves single URLs through it
 * ({@see \App\Support\PublicKolabLink::resolve()}). They must never drift: a Kolab the
 * sitemap advertises but the page 404s is worse than one that was never listed.
 *
 * NOT reused here: {@see DiscoveryOpportunityService}. Its signature is
 * `discover(Profile $viewer, …)` because its whole job is viewer-relative ranking —
 * Fit %, category affinity, "past activity with this partner". A guest has no viewer,
 * so every one of those signals is undefined, and forcing a null viewer through it
 * would mean inventing a neutral profile to rank against. The public web wants the
 * opposite of a ranked feed anyway: the newest honest listings, in order.
 */
class PublicKolabFeedService
{
    /**
     * Kolabs per page on the listing.
     */
    public const PER_PAGE = 24;

    /**
     * The gate. Published, and still applicable.
     *
     * `withSelectableDates()` is not a nicety — ROLES §3.3 hides date-exhausted Kolabs
     * from Explore for both roles precisely so nobody lands on an empty date picker,
     * and a stranger arriving from Google deserves that same guarantee more, not less.
     *
     * @return Builder<Kolab>
     */
    public function publishable(): Builder
    {
        return Kolab::query()
            ->published()
            ->withSelectableDates()
            ->where(function (Builder $query): void {
                /*
                 * A presentability floor, and only that. A Kolab whose whole description
                 * is "testhj" is not something to show a stranger on the marketing site.
                 *
                 * Be clear about what this is NOT: it is not a junk detector, and it
                 * cannot become one. Some of the least presentable rows in production
                 * today are structurally complete — the app itself generates
                 * "Collaborate with {name} to promote our product." — so no rule over
                 * these columns can separate a real listing from a test account's.
                 * Deciding that needs a human, tracked as BE-FX-24; until then these
                 * pages are deliberately kept out of the index (see the `robots`
                 * attribute on the views and their absence from sitemap.xml).
                 */
                $query->whereRaw('LENGTH(TRIM(COALESCE(description, \'\'))) >= ?', [
                    (int) config('kolabing.public_kolabs.min_description_length'),
                ]);
            });
    }

    /**
     * The listing, newest first.
     *
     * @param  array{city?: string|null, intent?: string|null}  $filters
     * @return LengthAwarePaginator<int, Kolab>
     */
    public function paginate(array $filters = [], int $perPage = self::PER_PAGE): LengthAwarePaginator
    {
        $query = $this->publishable()
            ->with(['creatorProfile.businessProfile', 'creatorProfile.communityProfile']);

        if (($filters['city'] ?? null) !== null && $filters['city'] !== '') {
            $query->forCity((string) $filters['city']);
        }

        if (($filters['intent'] ?? null) !== null && $filters['intent'] !== '') {
            $query->where('intent_type', $filters['intent']);
        }

        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * A short strip for the homepage.
     *
     * @return Collection<int, Kolab>
     */
    public function highlights(int $limit = 6): Collection
    {
        return $this->publishable()
            ->with(['creatorProfile.businessProfile', 'creatorProfile.communityProfile'])
            ->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Cities that actually have a listing, for the filter row.
     *
     * `kolabs.preferred_city` is a free-text city *name*, not a `cities.id` — so this
     * returns the distinct names present, and only those that match a known city, which
     * drops the "Unknown" rows the older client wrote. A filter offering a city with
     * nothing in it just sends people to an empty page.
     *
     * @return Collection<int, string>
     */
    public function cities(): Collection
    {
        $used = $this->publishable()
            ->whereNotNull('preferred_city')
            ->distinct()
            ->pluck('preferred_city')
            ->filter()
            ->values();

        $known = City::query()->pluck('name')->all();

        return $used
            ->filter(fn (string $city): bool => in_array($city, $known, true))
            ->sort()
            ->values();
    }
}
