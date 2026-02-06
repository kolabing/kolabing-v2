<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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
     * Get the global leaderboard.
     *
     * GET /api/v1/leaderboard/global
     */
    public function globalLeaderboard(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $limit = min((int) $request->query('limit', '50'), 100);
        $limit = max($limit, 1);

        $leaderboard = $this->leaderboardService->getGlobalLeaderboard($limit);
        $myRank = $this->leaderboardService->getMyGlobalRank($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'leaderboard' => $leaderboard->values()->all(),
                'my_rank' => $myRank,
            ],
        ]);
    }
}
