<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CommunityResource;
use App\Http\Resources\Api\V1\CommunityRewardResource;
use App\Http\Resources\Api\V1\PartnerRewardResource;
use App\Models\PartnerReward;
use App\Models\Profile;
use App\Services\CommunityMembershipHydrator;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeRewardsOverviewController extends Controller
{
    public function __construct(
        private readonly CommunityMembershipHydrator $membershipHydrator,
    ) {}

    /**
     * GET /api/v1/me/rewards-overview
     * → { xp, partner_rewards:[…], communities:[{ community, my_points, rewards:[…] }] }
     *
     * `xp` is the GLOBAL XP balance (wallet). `partner_rewards` are read-only
     * (admin CRUD is a later phase). Each community block carries the member's
     * per-community points and that community's redeemable rewards.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $xp = (int) ($profile->wallet?->points ?? 0);

        $partnerRewards = PartnerReward::query()
            ->where('is_active', true)
            ->orderBy('cost_xp')
            ->get();

        $memberships = $profile->communityMemberships()
            ->where('status', CommunityMemberStatus::Active->value)
            ->with([
                'tier',
                'community.communityProfile',
                'community.rewards' => function (Builder $query): void {
                    $query->where('is_active', true)->orderBy('cost_points');
                },
            ])
            ->get();

        // Bulk-resolve the viewer-scoped CommunityResource fields (member count,
        // points, tier, …) once, and reuse the returned points map for the
        // per-community balance + reward affordability — no per-community N+1.
        $balances = $this->membershipHydrator->hydrate($profile, $memberships);

        $communities = $memberships
            ->filter(fn ($m) => $m->community !== null)
            ->map(function ($membership) use ($balances): array {
                $community = $membership->community;
                $myPoints = $balances[$community->id] ?? 0;

                $rewards = $community->rewards
                    ->map(function ($reward) use ($myPoints): mixed {
                        $inStock = $reward->stock === null || $reward->stock > 0;
                        $reward->setAttribute('affordable', $myPoints >= $reward->cost_points && $inStock);

                        return $reward;
                    });

                return [
                    'community' => new CommunityResource($community),
                    'my_points' => $myPoints,
                    'rewards' => CommunityRewardResource::collection($rewards),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'xp' => $xp,
                'partner_rewards' => PartnerRewardResource::collection($partnerRewards),
                'communities' => $communities,
            ],
        ]);
    }
}
