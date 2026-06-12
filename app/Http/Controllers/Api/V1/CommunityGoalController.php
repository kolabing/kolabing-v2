<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunityGoalRequest;
use App\Http\Requests\Api\V1\UpdateCommunityGoalRequest;
use App\Http\Resources\Api\V1\CommunityGoalResource;
use App\Models\Community;
use App\Models\CommunityGoal;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityGoalController extends Controller
{
    /**
     * GET /api/v1/communities/{community}/goals — any member/viewer.
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CommunityGoalResource::collection(
                $community->goals()->orderByDesc('created_at')->get()
            ),
        ]);
    }

    /**
     * POST /api/v1/communities/{community}/goals (owner / can_manage).
     */
    public function store(StoreCommunityGoalRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $goal = $community->goals()->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CommunityGoalResource($goal),
        ], 201);
    }

    /**
     * PUT /api/v1/goals/{goal} (owner / can_manage).
     */
    public function update(UpdateCommunityGoalRequest $request, CommunityGoal $goal): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $goal->community)) {
            return $this->forbidden();
        }

        $goal->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CommunityGoalResource($goal->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/goals/{goal} (owner / can_manage).
     */
    public function destroy(Request $request, CommunityGoal $goal): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $goal->community)) {
            return $this->forbidden();
        }

        $goal->delete();

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
