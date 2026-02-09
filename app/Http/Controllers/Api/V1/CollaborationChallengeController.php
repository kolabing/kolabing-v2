<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCollaborationChallengeRequest;
use App\Http\Requests\Api\V1\SyncCollaborationChallengesRequest;
use App\Http\Resources\Api\V1\ChallengeResource;
use App\Models\Collaboration;
use App\Models\Profile;
use App\Services\CollaborationChallengeService;
use Illuminate\Http\JsonResponse;

class CollaborationChallengeController extends Controller
{
    public function __construct(
        private readonly CollaborationChallengeService $service
    ) {}

    /**
     * Sync selected challenges for a collaboration.
     *
     * PUT /api/v1/collaborations/{collaboration}/challenges
     */
    public function sync(SyncCollaborationChallengesRequest $request, Collaboration $collaboration): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $collaboration)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $selectedIds = $this->service->syncChallenges(
            $collaboration,
            $request->validated('selected_challenge_ids')
        );

        return response()->json([
            'success' => true,
            'message' => 'Challenges updated successfully.',
            'data' => [
                'selected_challenge_ids' => $selectedIds,
            ],
        ]);
    }

    /**
     * Create a custom challenge for a collaboration.
     *
     * POST /api/v1/collaborations/{collaboration}/challenges
     */
    public function store(StoreCollaborationChallengeRequest $request, Collaboration $collaboration): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $collaboration)) {
            return response()->json(['success' => false, 'message' => 'Forbidden.'], 403);
        }

        $challenge = $this->service->createCustomChallenge(
            $collaboration,
            $request->validated()
        );

        return response()->json([
            'success' => true,
            'data' => new ChallengeResource($challenge),
        ], 201);
    }
}
