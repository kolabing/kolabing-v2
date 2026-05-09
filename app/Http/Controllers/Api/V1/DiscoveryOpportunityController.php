<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DiscoverOpportunityRequest;
use App\Http\Resources\Api\V1\DiscoveryOpportunityCollection;
use App\Models\Profile;
use App\Services\DiscoveryOpportunityService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class DiscoveryOpportunityController extends Controller
{
    public function __construct(
        private readonly DiscoveryOpportunityService $discoveryOpportunityService,
    ) {}

    public function __invoke(DiscoverOpportunityRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $items = [];

        try {
            $result = $this->discoveryOpportunityService->discover(
                $profile,
                $request->validated(),
                (int) $request->validated('per_page', 15),
            );
            $items = (new DiscoveryOpportunityCollection($result['paginator']->getCollection()))
                ->resolve($request);
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'data' => $items,
                'meta' => $result['meta'],
            ],
            'meta' => $result['meta'],
        ]);
    }
}
