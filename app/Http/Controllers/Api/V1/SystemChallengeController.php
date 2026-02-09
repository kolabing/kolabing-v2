<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ChallengeResource;
use App\Models\Challenge;
use Illuminate\Http\JsonResponse;

class SystemChallengeController extends Controller
{
    /**
     * List all system challenges.
     *
     * GET /api/v1/challenges/system
     */
    public function __invoke(): JsonResponse
    {
        $challenges = Challenge::query()
            ->where('is_system', true)
            ->orderBy('category')
            ->orderBy('difficulty')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ChallengeResource::collection($challenges),
        ]);
    }
}
