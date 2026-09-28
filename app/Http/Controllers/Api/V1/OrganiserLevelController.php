<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\Incentives\OrganiserLevelService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganiserLevelController extends Controller
{
    public function __construct(private readonly OrganiserLevelService $levels) {}

    /**
     * GET /api/v1/me/organiser-level — the organiser's level card
     * (community profiles only).
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isCommunity()) {
            return response()->json([
                'success' => false,
                'message' => __('Organiser levels are only available to community profiles.'),
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => $this->levels->payload($this->levels->current($profile)),
        ]);
    }
}
