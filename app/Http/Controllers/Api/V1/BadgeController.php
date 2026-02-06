<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BadgeAwardResource;
use App\Http\Resources\Api\V1\BadgeResource;
use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BadgeController extends Controller
{
    /**
     * List all system badges.
     *
     * GET /api/v1/badges
     */
    public function index(): JsonResponse
    {
        $badges = Badge::all();

        return response()->json([
            'success' => true,
            'data' => ['badges' => BadgeResource::collection($badges)],
        ]);
    }

    /**
     * List the authenticated user's awarded badges.
     *
     * GET /api/v1/me/badges
     */
    public function myBadges(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $awards = BadgeAward::query()
            ->where('profile_id', $profile->id)
            ->with('badge')
            ->orderByDesc('awarded_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ['badges' => BadgeAwardResource::collection($awards)],
        ]);
    }
}
