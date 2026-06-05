<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventSignupStatus;
use App\Enums\EventVisibility;
use App\Models\Event;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class EventDiscoveryService
{
    /**
     * Attendee-facing PUBLIC EVENTS feed (Batch 3): upcoming `public` events any
     * attendee may discover and RSVP to, regardless of community membership.
     *
     * Eager-loads the owning community + photos for the card summary and counts
     * the `going` sign-ups for the social-proof badge.
     *
     * @return LengthAwarePaginator<Event>
     */
    public function discoverPublicUpcoming(int $perPage = 15): LengthAwarePaginator
    {
        return Event::query()
            ->where('visibility', EventVisibility::Public->value)
            ->whereNotNull('community_id')
            ->where(function ($query): void {
                $query
                    ->where('ends_at', '>', now())
                    ->orWhere(function ($inner): void {
                        $inner->whereNull('ends_at')->where('starts_at', '>', now());
                    })
                    ->orWhere(function ($inner): void {
                        $inner->whereNull('ends_at')->whereNull('starts_at')
                            ->where('event_date', '>=', now()->toDateString());
                    });
            })
            ->withCount(['signups as going_count' => function ($query): void {
                $query->where('status', EventSignupStatus::Going->value);
            }])
            ->with(['photos', 'community'])
            ->orderByRaw('COALESCE(starts_at, event_date) asc')
            ->paginate($perPage);
    }

    /**
     * Find active events near a given latitude/longitude within a radius (km).
     *
     * Uses the Haversine formula to calculate great-circle distances.
     * The query approach varies by database driver for compatibility.
     *
     * @return LengthAwarePaginator<Event>
     */
    public function discoverNearby(
        float $lat,
        float $lng,
        float $radiusKm = 50.0,
        int $perPage = 10
    ): LengthAwarePaginator {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            return $this->discoverNearbySqlite($lat, $lng, $radiusKm, $perPage);
        }

        return $this->discoverNearbyPostgres($lat, $lng, $radiusKm, $perPage);
    }

    /**
     * PostgreSQL implementation using native trigonometric functions.
     *
     * @return LengthAwarePaginator<Event>
     */
    private function discoverNearbyPostgres(
        float $lat,
        float $lng,
        float $radiusKm,
        int $perPage
    ): LengthAwarePaginator {
        $haversine = '(
            6371 * acos(
                cos(radians(?)) * cos(radians(location_lat)) *
                cos(radians(location_lng) - radians(?)) +
                sin(radians(?)) * sin(radians(location_lat))
            )
        )';

        return Event::query()
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->where('is_active', true)
            ->selectRaw("*, {$haversine} AS distance_km", [$lat, $lng, $lat])
            ->whereRaw("{$haversine} <= ?", [$lat, $lng, $lat, $radiusKm])
            ->orderBy('distance_km')
            ->with(['photos', 'profile'])
            ->paginate($perPage);
    }

    /**
     * SQLite-compatible implementation using bounding box approximation.
     *
     * Uses a latitude/longitude bounding box for the initial filter,
     * then calculates the precise Haversine distance in PHP.
     *
     * @return LengthAwarePaginator<Event>
     */
    private function discoverNearbySqlite(
        float $lat,
        float $lng,
        float $radiusKm,
        int $perPage
    ): LengthAwarePaginator {
        $latDelta = $radiusKm / 111.0;
        $lngDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));

        $minLat = $lat - $latDelta;
        $maxLat = $lat + $latDelta;
        $minLng = $lng - $lngDelta;
        $maxLng = $lng + $lngDelta;

        $paginator = Event::query()
            ->whereNotNull('location_lat')
            ->whereNotNull('location_lng')
            ->where('is_active', true)
            ->whereBetween('location_lat', [$minLat, $maxLat])
            ->whereBetween('location_lng', [$minLng, $maxLng])
            ->with(['photos', 'profile'])
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
