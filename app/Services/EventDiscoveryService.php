<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Support\CommunityTypeVocabulary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class EventDiscoveryService
{
    /**
     * Great-circle distance in km from a bound (lat, lng) to `events.location_*`,
     * as the `atan2`/`asin` form of the Haversine formula. Bindings, in order:
     * `[lat, lat, lng]`.
     *
     * Portable on purpose: `radians`, `sin`, `cos`, `power`, `sqrt` and `asin` are
     * standard in PostgreSQL and available in SQLite from 3.35 (math functions),
     * so the SAME expression runs in production and under `php artisan test`.
     * `EventDiscoverySqlDialectTest` fails loudly if a build lacks them.
     *
     * Deliberately NOT the law-of-cosines `6371 * acos(cos·cos·cos + sin·sin)`
     * this used to be: that inner term floats to 1 + ε for an event AT the query
     * point, and PostgreSQL's `acos()` raises "input is out of range" rather than
     * returning NaN. `asin(sqrt(a))` keeps its argument in range at zero distance.
     */
    private const HAVERSINE_KM = '(
            2 * 6371 * asin(
                sqrt(
                    power(sin(radians(location_lat - ?) / 2), 2)
                    + cos(radians(?)) * cos(radians(location_lat))
                    * power(sin(radians(location_lng - ?) / 2), 2)
                )
            )
        )';

    /**
     * Discover active events, optionally near a lat/lng and optionally filtered by
     * the host community's city / type, and by date (today | upcoming).
     *
     * Geo filtering is applied only when both $lat and $lng are provided; a
     * city_id alone (no coordinates) drives a non-geo, city-scoped query.
     *
     * @param  array{city_id?: ?string, date?: ?string, type?: ?string}  $filters
     * @return LengthAwarePaginator<Event>
     */
    public function discover(
        ?float $lat,
        ?float $lng,
        float $radiusKm = 50.0,
        int $perPage = 10,
        array $filters = []
    ): LengthAwarePaginator {
        $hasGeo = $lat !== null && $lng !== null;

        if ($hasGeo) {
            return $this->discoverNearby($lat, $lng, $radiusKm, $perPage, $filters);
        }

        return $this->discoverFiltered($perPage, $filters);
    }

    /**
     * Find events near a given latitude/longitude within a radius (km), nearest
     * first, with a transient `distance_km` on every row.
     *
     * ONE implementation, on every driver (BE-FX-23). The service used to branch:
     * SQLite got a bounding box plus a PHP calculation, Postgres got trigonometry
     * in SQL — and because `phpunit.xml` pins the suite to SQLite, the branch that
     * runs in production was never executed by CI. That is the same blind spot
     * that let BE-FX-12 ship a `max(uuid)` Postgres 500.
     *
     * The distance STAYS in SQL rather than moving to PHP, because filtering and
     * ordering by it is what makes this query bounded: `paginate()` resolves to a
     * COUNT plus a LIMIT/OFFSET over the radius. Computing it in PHP would mean
     * loading every candidate inside the radius (client-supplied, up to 200 km)
     * into memory on each request just to sort and slice it — a real regression,
     * and the reason the old SQLite branch was wrong in two further ways: it
     * paginated BEFORE filtering, so the exact-radius filter and the distance sort
     * only ever applied within one page, and `total` counted bounding-box corners
     * that were never returned.
     *
     * @param  array{city_id?: ?string, date?: ?string, type?: ?string}  $filters
     * @return LengthAwarePaginator<Event>
     */
    public function discoverNearby(
        float $lat,
        float $lng,
        float $radiusKm = 50.0,
        int $perPage = 10,
        array $filters = []
    ): LengthAwarePaginator {
        $haversine = self::HAVERSINE_KM;

        return $this->baseQuery($filters)
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->select('events.*')
            ->selectRaw("{$haversine} AS distance_km", [$lat, $lat, $lng])
            // CAST is load-bearing, not decoration. PDO binds a float as a STRING,
            // and SQLite compares across storage classes — every numeric value is
            // "less than" any text value, so `179.3 <= '50'` is TRUE and the radius
            // filter silently passes everything. PostgreSQL infers float8 from
            // context and was never affected, which is exactly the class of
            // divergence this ticket is about. `double precision` carries REAL
            // affinity in SQLite and is the native type in PostgreSQL.
            ->whereRaw("{$haversine} <= CAST(? AS double precision)", [$lat, $lat, $lng, $radiusKm])
            ->orderBy('distance_km')
            ->paginate($perPage);
    }

    /**
     * Non-geo discovery: city / date / type filters only, ordered by start.
     *
     * @param  array{city_id?: ?string, date?: ?string, type?: ?string}  $filters
     * @return LengthAwarePaginator<Event>
     */
    private function discoverFiltered(int $perPage, array $filters): LengthAwarePaginator
    {
        $query = $this->baseQuery($filters);

        return $query
            ->orderByRaw('COALESCE(starts_at, event_date) ASC')
            ->paginate($perPage);
    }

    /**
     * The shared base query: discoverable PUBLIC events, host-community + city
     * eager-loaded, with the city / date / type filters applied.
     *
     * Gate (changed for the empty-discover bug): we DO NOT require is_active here.
     * Community upcoming events are created is_active=false (EventService never
     * sets it), so the old blanket `where('is_active', true)` hid every upcoming
     * event from discover. The real visibility contract is `visibility=public`
     * (members/tier never leak) plus the future-inclusive date floor in
     * applyDateFilter (effective date >= today, all branches). is_active was an
     * artefact of the old geo "active now" path; the public/city upcoming surface
     * is governed entirely by visibility + date, so dropping it here is safe.
     *
     * @param  array{city_id?: ?string, date?: ?string, type?: ?string}  $filters
     * @return Builder<Event>
     */
    private function baseQuery(array $filters): Builder
    {
        $query = Event::query()
            // Discover is a PUBLIC surface: only events explicitly marked public are
            // visible to non-members. members/tier events never leak here.
            ->where('visibility', EventVisibility::Public->value)
            ->with(['photos', 'profile', 'city', 'community.communityProfile.city']);

        $this->applyFilters($query, $filters);

        return $query;
    }

    /**
     * Apply the city / date / type filters to a query.
     *
     * - city_id  → the event's EFFECTIVE city is that city. Effective city =
     *   events.city_id when set, ELSE the host community's
     *   community_profiles.city_id (events.community_id → communities
     *   .community_profile_id → community_profiles.city_id). So an event with its
     *   own city_id matches even when its community has no profile (e.g. a
     *   leader-created type=other community with community_profile_id=null).
     * - type     → host community's type matches the slug (separator-tolerant).
     *   Join path: events.community_id → communities.type.
     * - date     → restricts on the effective start date COALESCE(starts_at,
     *   event_date) — the same column the resource exposes. See applyDateFilter
     *   for the exact range boundaries.
     *
     * @param  Builder<Event>  $query
     * @param  array{city_id?: ?string, date?: ?string, type?: ?string}  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $cityId = $filters['city_id'] ?? null;
        if (is_string($cityId) && $cityId !== '') {
            // Effective-city match: the event's own city_id, OR (when it has none)
            // the host community's profile city. An explicit events.city_id always
            // wins, so an event pinned to a city shows even if its community lacks
            // a profile.
            $query->where(function (Builder $outer) use ($cityId): void {
                $outer->where('city_id', $cityId)
                    ->orWhere(function (Builder $fallback) use ($cityId): void {
                        $fallback->whereNull('city_id')
                            ->whereHas('community.communityProfile', function (Builder $sub) use ($cityId): void {
                                $sub->where('city_id', $cityId);
                            });
                    });
            });
        }

        $type = $filters['type'] ?? null;
        if (is_string($type) && $type !== '') {
            $variants = CommunityTypeVocabulary::variants($type);
            $query->whereHas('community', function (Builder $sub) use ($variants): void {
                $sub->whereIn('type', $variants);
            });
        }

        $this->applyDateFilter($query, $filters['date'] ?? null);
    }

    /**
     * Apply the `date` range filter on the effective start date,
     * DATE(COALESCE(starts_at, event_date)) — the same column the resource reads.
     *
     * Boundaries (all inclusive, evaluated against "now" in the app timezone):
     * - today    → effective date == today.
     * - week     → this ISO week, Monday .. Sunday (Carbon startOfWeek/endOfWeek).
     * - weekend  → this week's Saturday .. Sunday.
     * - month    → first .. last day of the current calendar month.
     * - upcoming → today onward (all future, the default). This is what fixes the
     *   "nothing shows" bug: future events are INCLUDED, only the past is dropped.
     * - null / unknown → no date restriction (still future-inclusive).
     *
     * @param  Builder<Event>  $query
     */
    private function applyDateFilter(Builder $query, ?string $date): void
    {
        $effective = 'DATE(COALESCE(starts_at, event_date))';

        switch ($date) {
            case 'today':
                $query->whereRaw("{$effective} = ?", [now()->toDateString()]);
                break;

            case 'week':
                $query->whereRaw("{$effective} BETWEEN ? AND ?", [
                    now()->startOfWeek()->toDateString(),
                    now()->endOfWeek()->toDateString(),
                ]);
                break;

            case 'weekend':
                $query->whereRaw("{$effective} BETWEEN ? AND ?", [
                    now()->startOfWeek()->addDays(5)->toDateString(), // Saturday
                    now()->endOfWeek()->toDateString(),                // Sunday
                ]);
                break;

            case 'month':
                $query->whereRaw("{$effective} BETWEEN ? AND ?", [
                    now()->startOfMonth()->toDateString(),
                    now()->endOfMonth()->toDateString(),
                ]);
                break;

            case 'upcoming':
            default:
                // All future events, today onward — fixes the empty-discover bug.
                $query->whereRaw("{$effective} >= ?", [now()->toDateString()]);
                break;
        }
    }
}
