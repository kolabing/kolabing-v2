<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Support\CommunityTypeVocabulary;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EventDiscoveryService
{
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
     * Find active events near a given latitude/longitude within a radius (km).
     *
     * Uses the Haversine formula to calculate great-circle distances.
     * The query approach varies by database driver for compatibility.
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
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return $this->discoverNearbySqlite($lat, $lng, $radiusKm, $perPage, $filters);
        }

        return $this->discoverNearbyPostgres($lat, $lng, $radiusKm, $perPage, $filters);
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

    /**
     * PostgreSQL implementation using native trigonometric functions.
     *
     * @param  array{city_id?: ?string, date?: ?string, type?: ?string}  $filters
     * @return LengthAwarePaginator<Event>
     */
    private function discoverNearbyPostgres(
        float $lat,
        float $lng,
        float $radiusKm,
        int $perPage,
        array $filters = []
    ): LengthAwarePaginator {
        $haversine = '(
            6371 * acos(
                cos(radians(?)) * cos(radians(location_lat)) *
                cos(radians(location_lng) - radians(?)) +
                sin(radians(?)) * sin(radians(location_lat))
            )
        )';

        $query = $this->baseQuery($filters)
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->select('events.*')
            ->selectRaw("{$haversine} AS distance_km", [$lat, $lng, $lat])
            ->whereRaw("{$haversine} <= ?", [$lat, $lng, $lat, $radiusKm])
            ->orderBy('distance_km');

        return $query->paginate($perPage);
    }

    /**
     * SQLite-compatible implementation using bounding box approximation.
     *
     * Uses a latitude/longitude bounding box for the initial filter,
     * then calculates the precise Haversine distance in PHP.
     *
     * @param  array{city_id?: ?string, date?: ?string, type?: ?string}  $filters
     * @return LengthAwarePaginator<Event>
     */
    private function discoverNearbySqlite(
        float $lat,
        float $lng,
        float $radiusKm,
        int $perPage,
        array $filters = []
    ): LengthAwarePaginator {
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));

        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;

        $paginator = $this->baseQuery($filters)
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->whereBetween('location_lat', [$minLat, $maxLat])
            ->whereBetween('location_lng', [$minLng, $maxLng])
            ->paginate($perPage);

        /** @var \Illuminate\Support\Collection<int, Event> $filtered */
        $filtered = collect($paginator->items())->map(function (Event $event) use ($lat, $lng): Event {
            $event->setAttribute('distance_km', $this->haversineDistance(
                $lat,
                $lng,
                (float) $event->location_lat,
                (float) $event->location_lng
            ));

            return $event;
        })->filter(function (Event $event) use ($radiusKm): bool {
            return $event->distance_km <= $radiusKm;
        })->sortBy('distance_km')->values();

        $paginator->setCollection($filtered);

        return $paginator;
    }

    /**
     * Calculate the Haversine distance between two points in km.
     */
    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lngDelta / 2) * sin($lngDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
