<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\InitiateChallengeRequest;
use App\Http\Resources\Api\V1\ChallengeCompletionResource;
use App\Models\ChallengeCompletion;
use App\Models\Profile;
use App\Services\ChallengeCompletionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeCompletionController extends Controller
{
    public function __construct(
        private readonly ChallengeCompletionService $challengeCompletionService
    ) {}

    /**
     * Initiate a peer-to-peer challenge.
     *
     * POST /api/v1/challenges/initiate
     */
    public function initiate(InitiateChallengeRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $completion = $this->challengeCompletionService->initiate($profile, $request->validated());

            return response()->json([
                'success' => true,
                'message' => __('Challenge initiated successfully.'),
                'data' => new ChallengeCompletionResource($completion),
            ], 201);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * Verify a pending challenge completion.
     *
     * POST /api/v1/challenge-completions/{challengeCompletion}/verify
     */
    public function verify(Request $request, ChallengeCompletion $challengeCompletion): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $completion = $this->challengeCompletionService->verify($profile, $challengeCompletion);

            return response()->json([
                'success' => true,
                'message' => __('Challenge verified successfully.'),
                'data' => new ChallengeCompletionResource($completion),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * Reject a pending challenge completion.
     *
     * POST /api/v1/challenge-completions/{challengeCompletion}/reject
     */
    public function reject(Request $request, ChallengeCompletion $challengeCompletion): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $completion = $this->challengeCompletionService->reject($profile, $challengeCompletion);

            return response()->json([
                'success' => true,
                'message' => __('Challenge rejected.'),
                'data' => new ChallengeCompletionResource($completion),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }

    /**
     * Get the authenticated user's challenge completions.
     *
     * GET /api/v1/me/challenge-completions
     */
    public function myCompletions(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $perPage = min((int) $request->query('limit', '10'), 50);

        $paginator = $this->challengeCompletionService->getMyCompletions($profile, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'completions' => ChallengeCompletionResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }
}
