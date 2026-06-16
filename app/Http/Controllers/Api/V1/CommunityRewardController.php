<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunityRewardRequest;
use App\Http\Requests\Api\V1\UpdateCommunityRewardRequest;
use App\Http\Resources\Api\V1\CommunityRewardResource;
use App\Models\Community;
use App\Models\CommunityReward;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityRewardController extends Controller
{
    /**
     * GET /api/v1/communities/{community}/rewards — any member/viewer.
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CommunityRewardResource::collection(
                $community->rewards()->orderByDesc('created_at')->get()
            ),
        ]);
    }

    /**
     * POST /api/v1/communities/{community}/rewards (owner / can_manage).
     */
    public function store(StoreCommunityRewardRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $reward = $community->rewards()->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CommunityRewardResource($reward),
        ], 201);
    }

    /**
     * PUT /api/v1/rewards/{reward} (owner / can_manage).
     */
    public function update(UpdateCommunityRewardRequest $request, CommunityReward $reward): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $reward->community)) {
            return $this->forbidden();
        }

        $reward->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CommunityRewardResource($reward->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/rewards/{reward} (owner / can_manage).
     */
    public function destroy(Request $request, CommunityReward $reward): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $reward->community)) {
            return $this->forbidden();
        }

        $reward->delete();

        return response()->json(['success' => true, 'data' => null]);
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('You are not authorized to manage this community.'),
        ], 403);
    }
}
