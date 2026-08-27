<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ChallengeRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ClaimEncounterRequest;
use App\Http\Requests\Api\V1\CreateGhostInviteRequest;
use App\Http\Resources\Api\V1\EncounterResource;
use App\Models\Challenge;
use App\Models\Event;
use App\Models\Profile;
use App\Services\EncounterService;
use Illuminate\Http\JsonResponse;

/**
 * The People Layer's write surface (#244, #246).
 */
class EncounterController extends Controller
{
    public function __construct(
        private readonly EncounterService $encounterService,
    ) {}

    /**
     * Record a challenge with someone who does not have the app, and hand back
     * an invite that can bring them in.
     *
     * POST /api/v1/encounters/ghost
     *
     * No XP is paid here — `pending_points` is what is waiting, and it is
     * released to both sides only when the invite is claimed.
     */
    public function ghost(CreateGhostInviteRequest $request): JsonResponse
    {
        /** @var Profile $inviter */
        $inviter = $request->user();

        /** @var Event $event */
        $event = Event::query()->findOrFail($request->string('event_id')->toString());
        /** @var Challenge $challenge */
        $challenge = Challenge::query()->findOrFail($request->string('challenge_id')->toString());

        try {
            $ghost = $this->encounterService->createGhostInvite(
                $inviter,
                $event,
                $challenge,
                $request->string('ghost_name')->toString(),
                $request->input('ghost_contact'),
            );
        } catch (ChallengeRuleException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->reason,
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'encounter' => new EncounterResource($ghost->load(['event', 'community'])),
                'claim_code' => $ghost->ghost_claim_token,
                'invite_url' => $this->encounterService->inviteUrl($ghost),
                'expires_at' => $ghost->expires_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Redeem an invite code.
     *
     * POST /api/v1/encounters/claim
     *
     * Both sides are paid what was promised at invite time. Refusals carry a
     * machine-readable `error` so the app can say something specific rather
     * than "something went wrong".
     */
    public function claim(ClaimEncounterRequest $request): JsonResponse
    {
        /** @var Profile $claimer */
        $claimer = $request->user();

        try {
            $encounter = $this->encounterService->claim(
                $claimer,
                $request->string('claim_code')->toString(),
            );
        } catch (ChallengeRuleException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->reason,
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => new EncounterResource(
                $encounter->load(['profile', 'event', 'community'])
            ),
        ]);
    }
}
