<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\GamificationBadgeSlug;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreWithdrawalRequest;
use App\Http\Resources\Api\V1\GamificationBadgeResource;
use App\Http\Resources\Api\V1\PointLedgerResource;
use App\Http\Resources\Api\V1\ReferralCodeResource;
use App\Http\Resources\Api\V1\WalletResource;
use App\Http\Resources\Api\V1\WithdrawalRequestResource;
use App\Models\EarnedBadge;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\ReferralCode;
use App\Services\GamificationWalletService;
use App\Services\WithdrawalRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GamificationController extends Controller
{
    public function __construct(
        private readonly GamificationWalletService $walletService,
        private readonly WithdrawalRequestService $withdrawalRequestService,
    ) {}

    /**
     * GET /api/v1/gamification/wallet
     */
    public function wallet(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $wallet = $this->walletService->getOrCreateWallet($profile->id);

        return response()->json([
            'success' => true,
            'data' => new WalletResource($wallet),
        ]);
    }

    /**
     * GET /api/v1/gamification/ledger
     */
    public function ledger(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $perPage = min((int) $request->query('per_page', 20), 100);

        $entries = PointLedger::query()
            ->where('profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => PointLedgerResource::collection($entries),
            'meta' => [
                'current_page' => $entries->currentPage(),
                'last_page' => $entries->lastPage(),
                'per_page' => $entries->perPage(),
                'total' => $entries->total(),
            ],
        ]);
    }

    /**
     * GET /api/v1/gamification/badges
     */
    public function badges(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $earnedBadges = EarnedBadge::query()
            ->where('profile_id', $profile->id)
            ->get()
            ->keyBy(fn (EarnedBadge $b) => $b->badge_slug->value);

        $badges = collect(GamificationBadgeSlug::cases())->map(function (GamificationBadgeSlug $slug) use ($earnedBadges) {
            return [
                'slug' => $slug,
                'earned_badge' => $earnedBadges->get($slug->value),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => GamificationBadgeResource::collection($badges),
        ]);
    }

    /**
     * GET /api/v1/gamification/referral-code
     */
    public function referralCode(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $code = ReferralCode::query()->firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'code' => ReferralCode::generateCode(),
                'total_conversions' => 0,
                'total_points_earned' => 0,
            ]
        );

        return response()->json([
            'success' => true,
            'data' => new ReferralCodeResource($code),
        ]);
    }

    /**
     * POST /api/v1/gamification/withdrawal
     */
    public function withdrawal(StoreWithdrawalRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        try {
            $withdrawalRequest = $this->withdrawalRequestService->submit($profile, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], str_contains($e->getMessage(), 'pending') ? 409 : 400);
        }

        return response()->json([
            'success' => true,
            'data' => new WithdrawalRequestResource($withdrawalRequest),
            'message' => 'Withdrawal request submitted. Processing within 5-7 business days.',
        ], 201);
    }
}
