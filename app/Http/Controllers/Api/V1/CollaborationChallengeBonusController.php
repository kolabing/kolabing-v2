<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpsertCollaborationChallengeBonusRequest;
use App\Models\Challenge;
use App\Models\Collaboration;
use App\Models\Profile;
use App\Services\CollaborationChallengeBonusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CollaborationChallengeBonusController extends Controller
{
    public function __construct(
        private readonly CollaborationChallengeBonusService $service,
    ) {}

    /**
     * Upsert the bonus a business is offering on top of a selected challenge.
     *
     * PUT /api/v1/collaborations/{collaboration}/challenges/{challenge}/bonus
     */
    public function upsert(
        UpsertCollaborationChallengeBonusRequest $request,
        Collaboration $collaboration,
        Challenge $challenge,
    ): JsonResponse {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->callerIsBusinessParticipant($profile, $collaboration)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the business participant can set a challenge bonus.',
            ], 403);
        }

        try {
            $bonus = $this->service->upsert($collaboration, $challenge, $profile, [
                'bonus_type' => $request->validated('bonus_type'),
                'bonus_value' => $request->validated('bonus_value'),
                'bonus_description' => $request->validated('bonus_description'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'collaboration_id' => $bonus->collaboration_id,
                'challenge_id' => $bonus->challenge_id,
                'bonus_type' => $bonus->bonus_type->value,
                'bonus_value' => $bonus->bonus_value,
                'bonus_description' => $bonus->bonus_description,
                'set_by_profile_id' => $bonus->set_by_profile_id,
            ],
        ]);
    }

    /**
     * DELETE /api/v1/collaborations/{collaboration}/challenges/{challenge}/bonus
     */
    public function destroy(
        Request $request,
        Collaboration $collaboration,
        Challenge $challenge,
    ): JsonResponse {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->callerIsBusinessParticipant($profile, $collaboration)) {
            return response()->json([
                'success' => false,
                'message' => 'Only the business participant can remove a challenge bonus.',
            ], 403);
        }

        $deleted = $this->service->remove($collaboration, $challenge);

        return response()->json([
            'success' => true,
            'data' => [
                'deleted' => $deleted,
            ],
        ]);
    }

    private function callerIsBusinessParticipant(Profile $profile, Collaboration $collaboration): bool
    {
        if (! $profile->isBusiness()) {
            return false;
        }

        return $collaboration->creator_profile_id === $profile->id
            || $collaboration->applicant_profile_id === $profile->id;
    }
}
