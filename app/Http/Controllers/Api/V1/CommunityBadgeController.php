<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCommunityBadgeRequest;
use App\Http\Requests\Api\V1\UpdateCommunityBadgeRequest;
use App\Http\Resources\Api\V1\CommunityBadgeResource;
use App\Models\Community;
use App\Models\CommunityBadge;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityBadgeController extends Controller
{
    /**
     * GET /api/v1/communities/{community}/badges — any member/viewer.
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => CommunityBadgeResource::collection(
                $community->badges()->orderByDesc('created_at')->get()
            ),
        ]);
    }

    /**
     * POST /api/v1/communities/{community}/badges (owner / can_manage).
     */
    public function store(StoreCommunityBadgeRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return $this->forbidden();
        }

        $badge = $community->badges()->create($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CommunityBadgeResource($badge),
        ], 201);
    }

    /**
     * PUT /api/v1/badges/{badge} (owner / can_manage).
     */
    public function update(UpdateCommunityBadgeRequest $request, CommunityBadge $badge): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $badge->community)) {
            return $this->forbidden();
        }

        $badge->update($request->validated());

        return response()->json([
            'success' => true,
            'data' => new CommunityBadgeResource($badge->fresh()),
        ]);
    }

    /**
     * DELETE /api/v1/badges/{badge} (owner / can_manage).
     */
    public function destroy(Request $request, CommunityBadge $badge): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $badge->community)) {
            return $this->forbidden();
        }

        $badge->delete();

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
