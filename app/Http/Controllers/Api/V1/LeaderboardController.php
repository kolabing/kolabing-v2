<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Event;
use App\Models\Profile;
use App\Services\LeaderboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaderboardController extends Controller
{
    public function __construct(
        private readonly LeaderboardService $leaderboardService
    ) {}

    /**
     * Get the leaderboard for a specific event.
     *
     * GET /api/v1/events/{event}/leaderboard
     */
    public function eventLeaderboard(Request $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $limit = min((int) $request->query('limit', '50'), 100);
        $limit = max($limit, 1);

        $leaderboard = $this->leaderboardService->getEventLeaderboard($event, $limit);
        $myRank = $this->leaderboardService->getMyEventRank($event, $profile);

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard->values()->all(),
                'my_rank' => $myRank,
            ],
        ]);
    }

    /**
     * Get the global leaderboard, or a chapter-scoped one when community_id is
     * passed (NF-6): the global leaderboard filtered to one community's members.
     *
     * GET /api/v1/leaderboard/global[?community_id={uuid}]
     */
    public function globalLeaderboard(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $limit = min((int) $request->query('limit', '50'), 100);
        $limit = max($limit, 1);

        $communityId = $request->query('community_id');

        if ($communityId !== null) {
            /** @var Community $community */
            $community = Community::query()->findOrFail($communityId);

            $leaderboard = $this->leaderboardService->getCommunityLeaderboard($community, $limit);
            $myRank = $this->leaderboardService->getMyCommunityRank($community, $profile);
        } else {
            $leaderboard = $this->leaderboardService->getGlobalLeaderboard($limit);
            $myRank = $this->leaderboardService->getMyGlobalRank($profile);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard->values()->all(),
                'my_rank' => $myRank,
            ],
        ]);
    }

    /**
     * The per-community POINTS leaderboard. Each row carries the member's
     * tier, badge_count, and points (the canonical community ranking).
     *
     * GET /api/v1/communities/{community}/leaderboard
     */
    public function communityLeaderboard(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $limit = min((int) $request->query('limit', '50'), 100);
        $limit = max($limit, 1);

        $leaderboard = $this->leaderboardService->getCommunityPointsLeaderboard($community, $limit);
        $myRank = $this->leaderboardService->getMyCommunityPointsRank($community, $profile);

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard->values()->all(),
                'my_rank' => $myRank,
            ],
        ]);
    }
}
