<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ConfirmRedeemRequest;
use App\Http\Resources\Api\V1\RewardClaimResource;
use App\Models\Profile;
use App\Models\RewardClaim;
use App\Services\RewardWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RewardWalletController extends Controller
{
    public function __construct(
        private readonly RewardWalletService $rewardWalletService
    ) {}

    /**
     * List the authenticated user's reward claims.
     *
     * GET /api/v1/me/rewards
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $perPage = min((int) $request->query('limit', '10'), 50);

        $paginator = $this->rewardWalletService->getMyRewards($profile, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'rewards' => RewardClaimResource::collection($paginator->items()),
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
     * Generate a redeem token (QR code content) for a reward claim.
     *
     * POST /api/v1/reward-claims/{rewardClaim}/generate-redeem-qr
     */
    public function generateRedeemQr(Request $request, RewardClaim $rewardClaim): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $claim = $this->rewardWalletService->generateRedeemToken($profile, $rewardClaim);

            return response()->json([
                'success' => true,
                'message' => __('Redeem QR generated successfully.'),
                'data' => new RewardClaimResource($claim),
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
     * Confirm redemption of a reward claim by the event organizer.
     *
     * POST /api/v1/reward-claims/confirm-redeem
     */
    public function confirmRedeem(ConfirmRedeemRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $claim = $this->rewardWalletService->confirmRedeem($profile, $request->validated('token'));

            return response()->json([
                'success' => true,
                'message' => __('Reward redeemed successfully.'),
                'data' => new RewardClaimResource($claim),
            ]);
        } catch (\InvalidArgumentException $e) {
            $statusCode = $e->getMessage() === 'Invalid redeem token.' ? 404 : 403;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }
    }
}
