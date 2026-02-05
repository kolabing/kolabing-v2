<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreChallengeRequest;
use App\Http\Requests\Api\V1\UpdateChallengeRequest;
use App\Http\Resources\Api\V1\ChallengeResource;
use App\Models\Challenge;
use App\Models\Event;
use App\Models\Profile;
use App\Services\ChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChallengeController extends Controller
{
    public function __construct(
        private readonly ChallengeService $challengeService
    ) {}

    /**
     * List system + event-specific challenges.
     *
     * GET /api/v1/events/{event}/challenges
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $perPage = min((int) $request->query('limit', '20'), 50);

        $paginator = $this->challengeService->listForEvent($event, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'challenges' => ChallengeResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    /**
     * Create a custom challenge for an event.
     *
     * POST /api/v1/events/{event}/challenges
     */
    public function store(StoreChallengeRequest $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->id !== $event->profile_id) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to create challenges for this event.'),
            ], 403);
        }

        $challenge = $this->challengeService->create($event, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Challenge created successfully.'),
            'data' => new ChallengeResource($challenge),
        ], 201);
    }

    /**
     * Update a custom challenge.
     *
     * PUT /api/v1/challenges/{challenge}
     */
    public function update(UpdateChallengeRequest $request, Challenge $challenge): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $challenge)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to update this challenge.'),
            ], 403);
        }

        $challenge = $this->challengeService->update($challenge, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Challenge updated successfully.'),
            'data' => new ChallengeResource($challenge),
        ]);
    }

    /**
     * Delete a custom challenge.
     *
     * DELETE /api/v1/challenges/{challenge}
     */
    public function destroy(Request $request, Challenge $challenge): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('delete', $challenge)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to delete this challenge.'),
            ], 403);
        }

        $this->challengeService->delete($challenge);

        return response()->json([
            'success' => true,
            'message' => __('Challenge deleted successfully.'),
        ]);
    }
}
