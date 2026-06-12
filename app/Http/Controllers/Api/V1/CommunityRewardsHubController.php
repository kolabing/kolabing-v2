<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommunityBadgeResource;
use App\Http\Resources\Api\V1\CommunityGoalResource;
use App\Http\Resources\Api\V1\CommunityRewardResource;
use App\Http\Resources\Api\V1\CommunityTierResource;
use App\Http\Resources\Api\V1\RewardRedemptionResource;
use App\Models\Community;
use App\Models\CommunityBadgeAward;
use App\Models\CommunityMember;
use App\Models\CommunityReward;
use App\Models\Profile;
use App\Services\CommunityBadgeService;
use App\Services\CommunityPointsService;
use App\Services\RewardRedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityRewardsHubController extends Controller
{
    public function __construct(
        private readonly CommunityPointsService $pointsService,
        private readonly CommunityBadgeService $badgeService,
        private readonly RewardRedemptionService $redemptionService,
    ) {}

    /**
     * GET /api/v1/communities/{community}/rewards-hub
     * → { my_points, my_tier, goals:[…+progress], badges:[…+earned], rewards:[…+affordable] }
     */
    public function show(Request $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        // Recompute-on-read: any newly satisfied goals/badges are caught here.
        $this->pointsService->completeGoals($community, $profile->id);
        $this->badgeService->evaluate($community, $profile->id);

        $myPoints = $this->pointsService->balance($community, $profile->id);
        $myTier = $this->myTier($community, $profile->id);

        $earnedBadgeIds = CommunityBadgeAward::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profile->id)
            ->pluck('community_badge_id')
            ->all();

        $goals = $community->goals()->where('is_active', true)->orderByDesc('created_at')->get()
            ->map(function ($goal) use ($community, $profile): mixed {
                $progress = $this->pointsService->goalProgress($community, $profile->id, $goal);
                $goal->setAttribute('progress', $progress);
                $goal->setAttribute('completed', $progress >= $goal->target);

                return $goal;
            });

        $badges = $community->badges()->where('is_active', true)->orderByDesc('created_at')->get()
            ->map(function ($badge) use ($earnedBadgeIds): mixed {
                $badge->setAttribute('earned', in_array($badge->id, $earnedBadgeIds, true));

                return $badge;
            });

        $rewards = $community->rewards()->where('is_active', true)->orderBy('cost_points')->get()
            ->map(function ($reward) use ($myPoints): mixed {
                $inStock = $reward->stock === null || $reward->stock > 0;
                $reward->setAttribute('affordable', $myPoints >= $reward->cost_points && $inStock);

                return $reward;
            });

        return response()->json([
            'success' => true,
            'data' => [
                'my_points' => $myPoints,
                'my_tier' => $myTier !== null ? new CommunityTierResource($myTier) : null,
                'goals' => CommunityGoalResource::collection($goals),
                'badges' => CommunityBadgeResource::collection($badges),
                'rewards' => CommunityRewardResource::collection($rewards),
            ],
        ]);
    }

    /**
     * POST /api/v1/communities/{community}/rewards/{reward}/redeem
     */
    public function redeem(Request $request, Community $community, CommunityReward $reward): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $redemption = $this->redemptionService->redeem($community, $reward, $profile->id);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => __('This reward does not belong to this community.'),
            ], 404);
        } catch (\DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $this->redeemErrorMessage($e->getMessage()),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'redemption' => new RewardRedemptionResource($redemption),
                'my_points' => $this->pointsService->balance($community, $profile->id),
            ],
        ], 201);
    }

    private function myTier(Community $community, string $profileId): mixed
    {
        $member = CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profileId)
            ->with('tier')
            ->first();

        return $member?->tier;
    }

    private function redeemErrorMessage(string $code): string
    {
        return match ($code) {
            'insufficient_points' => __('You do not have enough points to redeem this reward.'),
            'out_of_stock' => __('This reward is out of stock.'),
            'reward_inactive' => __('This reward is no longer available.'),
            default => __('This reward could not be redeemed.'),
        };
    }
}
