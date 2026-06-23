<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DiscoverEventsRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Services\EventDiscoveryService;
use App\Services\EventSignupService;
use Illuminate\Http\JsonResponse;

class EventDiscoveryController extends Controller
{
    public function __construct(
        private readonly EventDiscoveryService $discoveryService,
        private readonly EventSignupService $eventSignupService,
    ) {}

    public function __invoke(DiscoverEventsRequest $request): JsonResponse
    {
        $latRaw = $request->validated('lat');
        $lngRaw = $request->validated('lng');
        $lat = $latRaw !== null ? (float) $latRaw : null;
        $lng = $lngRaw !== null ? (float) $lngRaw : null;
        $radius = (float) ($request->validated('radius_km') ?? 50);
        $perPage = (int) ($request->validated('limit') ?? 10);

        /** @var array{city_id?: string, date?: string, type?: string} $filters */
        $filters = [
            'city_id' => $request->validated('city_id'),
            'date' => $request->validated('date'),
            'type' => $request->validated('type'),
        ];

        $paginator = $this->discoveryService->discover($lat, $lng, $radius, $perPage, $filters, $request->user());
        $this->eventSignupService->hydrateSummaries($paginator->items(), $request->user());

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
}
