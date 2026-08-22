<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Community;
use App\Models\Profile;
use App\Services\CommunityStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityStatsController extends Controller
{
    public function __construct(private readonly CommunityStatsService $stats) {}

    /**
     * GET /api/v1/communities/{community}/stats — the Hub health strip.
     *
     * Manage-gated (owner / can_manage). NEVER subscription-gated: a community
     * path must not hit a paywall (ROLES §8.4).
     */
    public function show(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to manage this community.'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->stats->forCommunity($community),
        ]);
    }
}
