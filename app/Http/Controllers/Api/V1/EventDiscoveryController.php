<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DiscoverEventsRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Http\Resources\Api\V1\PublicEventResource;
use App\Services\EventDiscoveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventDiscoveryController extends Controller
{
    public function __construct(
        private readonly EventDiscoveryService $discoveryService
    ) {}

    public function __invoke(DiscoverEventsRequest $request): JsonResponse
    {
        $lat = (float) $request->validated('lat');
        $lng = (float) $request->validated('lng');
        $radius = (float) ($request->validated('radius_km') ?? 50);
        $perPage = (int) ($request->validated('limit') ?? 10);

        $paginator = $this->discoveryService->discoverNearby($lat, $lng, $radius, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'events' => EventResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/events/discovery — attendee-facing PUBLIC EVENTS feed.
     * Returns UPCOMING `public` events any attendee may RSVP to (no membership),
     * each with a community summary and going count.
     */
    public function publicFeed(Request $request): JsonResponse
    {
        $perPage = (int) ($request->query('limit', '15'));
        $perPage = max(1, min($perPage, 50));

        $paginator = $this->discoveryService->discoverPublicUpcoming($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'events' => PublicEventResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }
}
