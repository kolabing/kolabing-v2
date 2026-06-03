<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunityTierRequest;
use App\Http\Requests\Api\V1\UpdateCommunityTierRequest;
use App\Http\Resources\Api\V1\CommunityTierResource;
use App\Models\Community;
use App\Models\CommunityTier;
use App\Models\Profile;
use App\Services\CommunityTierService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CommunityTierController extends Controller
{
    public function __construct(
        private readonly CommunityTierService $tierService
    ) {}

    /**
     * GET /api/v1/communities/{community}/tiers (rank desc).
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CommunityTierResource::collection($community->tiers),
        ]);
    }

    /**
     * POST /api/v1/communities/{community}/tiers (owner / can_manage).
     */
    public function store(StoreCommunityTierRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $tier = $this->tierService->create($community, $request->validated());

        return response()->json([
            'success' => true,
            'data' => new CommunityTierResource($tier),
        ], 201);
    }

    /**
     * PATCH /api/v1/tiers/{tier} (owner / can_manage).
     */
    public function update(UpdateCommunityTierRequest $request, CommunityTier $tier): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $tier->community)) {
            return $this->forbidden();
        }

        try {
            $tier = $this->tierService->update($tier, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => 'threshold_required_for_rule',
                'message' => __('A threshold is required for non-manual assignment rules.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new CommunityTierResource($tier),
        ]);
    }

    /**
     * DELETE /api/v1/tiers/{tier} (owner / can_manage). Cannot delete default.
     */
    public function destroy(Request $request, CommunityTier $tier): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $tier->community)) {
            return $this->forbidden();
        }

        try {
            $this->tierService->delete($tier);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => 'cannot_delete_default_tier',
                'message' => __('Promote another tier to default before deleting this one.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('You are not authorized to manage this community.'),
        ], 403);
    }
}
