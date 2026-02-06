<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\GamificationStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GamificationStatsController extends Controller
{
    public function __construct(
        private readonly GamificationStatsService $statsService
    ) {}

    /**
     * Get gamification stats for the authenticated user.
     *
     * GET /api/v1/me/gamification-stats
     */
    public function myStats(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $stats = $this->statsService->getStats($profile);

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    /**
     * Get the game card for a profile (public view).
     *
     * GET /api/v1/profiles/{profile}/game-card
     */
    public function gameCard(Profile $profile): JsonResponse
    {
        $data = $this->statsService->getGameCard($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'profile' => $data['profile'],
                'stats' => $data['stats'],
                'recent_badges' => $data['recent_badges'],
            ],
        ]);
    }
}
