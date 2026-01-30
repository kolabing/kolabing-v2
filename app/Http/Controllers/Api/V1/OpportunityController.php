<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOpportunityRequest;
use App\Http\Requests\Api\V1\UpdateOpportunityRequest;
use App\Http\Resources\Api\V1\OpportunityCollection;
use App\Http\Resources\Api\V1\OpportunityResource;
use App\Models\CollabOpportunity;
use App\Models\Profile;
use App\Services\OpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OpportunityController extends Controller
{
    public function __construct(
        private readonly OpportunityService $opportunityService,
    ) {}

    /**
     * Browse published opportunities with filters.
     *
     * GET /api/v1/opportunities
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $filters = [
            'creator_type' => $request->query('creator_type'),
            'categories' => $request->query('categories'),
            'city' => $request->query('city'),
            'venue_mode' => $request->query('venue_mode'),
            'availability_mode' => $request->query('availability_mode'),
            'date_from' => $request->query('availability_from'),
            'date_to' => $request->query('availability_to'),
            'search' => $request->query('search'),
        ];

        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 100);

        $opportunities = $this->opportunityService->browse($profile, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => new OpportunityCollection($opportunities),
            'meta' => [
                'current_page' => $opportunities->currentPage(),
                'last_page' => $opportunities->lastPage(),
                'per_page' => $opportunities->perPage(),
                'total' => $opportunities->total(),
            ],
        ]);
    }

    /**
     * List opportunities created by the authenticated user.
     *
     * GET /api/v1/me/opportunities
     */
    public function myOpportunities(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $filters = [
            'status' => $request->query('status'),
        ];

        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 100);

        $opportunities = $this->opportunityService->getMyOpportunities($profile, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => new OpportunityCollection($opportunities),
            'meta' => [
                'current_page' => $opportunities->currentPage(),
                'last_page' => $opportunities->lastPage(),
                'per_page' => $opportunities->perPage(),
                'total' => $opportunities->total(),
            ],
        ]);
    }

    /**
     * Get a single opportunity.
     *
     * GET /api/v1/opportunities/{opportunity}
     */
    public function show(Request $request, CollabOpportunity $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $opportunity)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this opportunity.'),
            ], 403);
        }

        $opportunity->load(['creatorProfile', 'applications']);

        return response()->json([
            'success' => true,
            'data' => new OpportunityResource($opportunity),
        ]);
    }

    /**
     * Create a new opportunity.
     *
     * POST /api/v1/opportunities
     */
    public function store(CreateOpportunityRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('create', CollabOpportunity::class)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to create opportunities.'),
            ], 403);
        }

        try {
            $opportunity = $this->opportunityService->create($profile, $request->validated());
            $opportunity->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Opportunity created successfully.'),
                'data' => new OpportunityResource($opportunity),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'requires_subscription' => true,
            ], 403);
        }
    }

    /**
     * Update an opportunity.
     *
     * PUT /api/v1/opportunities/{opportunity}
     */
    public function update(UpdateOpportunityRequest $request, CollabOpportunity $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $opportunity)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to update this opportunity.'),
            ], 403);
        }

        try {
            $opportunity = $this->opportunityService->update($opportunity, $request->validated());
            $opportunity->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Opportunity updated successfully.'),
                'data' => new OpportunityResource($opportunity),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete an opportunity.
     *
     * DELETE /api/v1/opportunities/{opportunity}
     */
    public function destroy(Request $request, CollabOpportunity $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('delete', $opportunity)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to delete this opportunity.'),
            ], 403);
        }

        try {
            $this->opportunityService->delete($opportunity);

            return response()->json([
                'success' => true,
                'message' => __('Opportunity deleted successfully.'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Publish a draft opportunity.
     *
     * POST /api/v1/opportunities/{opportunity}/publish
     */
    public function publish(Request $request, CollabOpportunity $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('publish', $opportunity)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to publish this opportunity.'),
            ], 403);
        }

        try {
            $opportunity = $this->opportunityService->publish($opportunity);
            $opportunity->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Opportunity published successfully.'),
                'data' => new OpportunityResource($opportunity),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Close a published opportunity.
     *
     * POST /api/v1/opportunities/{opportunity}/close
     */
    public function close(Request $request, CollabOpportunity $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('close', $opportunity)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to close this opportunity.'),
            ], 403);
        }

        try {
            $opportunity = $this->opportunityService->close($opportunity);
            $opportunity->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Opportunity closed successfully.'),
                'data' => new OpportunityResource($opportunity),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
