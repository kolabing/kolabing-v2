<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Profile;
use App\Services\CommunityFollowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Follow / unfollow a community (kolabing-app#138).
 *
 * No policy check: any signed-in profile may follow any community. Following
 * grants nothing that needs guarding — it is the deliberately frictionless
 * half of the follower/member split, and the leader is not asked, because a
 * follow is not a request.
 */
class CommunityFollowController extends Controller
{
    public function __construct(
        private readonly CommunityFollowService $service
    ) {}

    /**
     * POST /api/v1/communities/{community}/follow
     *
     * 201 the first time, 200 on a repeat — idempotent, so a double tap or a
     * retry never surfaces the unique constraint as a server error.
     */
    public function store(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        ['follower' => $follower, 'created' => $created] =
            $this->service->follow($community, $profile);

        return response()->json([
            'success' => true,
            'data' => [
                'community_id' => $community->id,
                'is_following' => true,
                'followed_at' => $follower->followed_at?->toIso8601String(),
                'followers_count' => $community->followers()->count(),
            ],
        ], $created ? 201 : 200);
    }

    /**
     * DELETE /api/v1/communities/{community}/follow
     *
     * Also idempotent: unfollowing something you do not follow is a no-op, not
     * a 404 — the caller's intent is already satisfied.
     */
    public function destroy(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->service->unfollow($community, $profile);

        return response()->json([
            'success' => true,
            'data' => [
                'community_id' => $community->id,
                'is_following' => false,
                'followers_count' => $community->followers()->count(),
            ],
        ]);
    }
}
