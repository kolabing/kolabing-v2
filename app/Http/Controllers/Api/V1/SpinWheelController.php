<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SpinWheelRequest;
use App\Http\Resources\Api\V1\RewardClaimResource;
use App\Models\ChallengeCompletion;
use App\Models\Profile;
use App\Services\SpinWheelService;
use Illuminate\Http\JsonResponse;

class SpinWheelController extends Controller
{
    public function __construct(
        private readonly SpinWheelService $spinWheelService
    ) {}

    /**
     * Spin the wheel after a verified challenge completion.
     *
     * POST /api/v1/rewards/spin
     */
    public function spin(SpinWheelRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $completion = ChallengeCompletion::query()->findOrFail(
            $request->validated('challenge_completion_id')
        );

        try {
            $result = $this->spinWheelService->spin($profile, $completion);

            if ($result['won']) {
                return response()->json([
                    'success' => true,
                    'message' => __('Congratulations! You won a reward!'),
                    'data' => [
                        'won' => true,
                        'reward_claim' => new RewardClaimResource($result['reward_claim']),
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => __('Better luck next time!'),
                'data' => [
                    'won' => false,
                    'reward_claim' => null,
                ],
            ]);
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
}
