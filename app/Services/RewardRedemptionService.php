<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityPointSource;
use App\Enums\RewardRedemptionStatus;
use App\Models\Community;
use App\Models\CommunityPointLedger;
use App\Models\CommunityPoints;
use App\Models\CommunityReward;
use App\Models\RewardRedemption;
use Illuminate\Support\Facades\DB;

class RewardRedemptionService
{
    /**
     * Redeem a community reward for a member: guards balance + stock, deducts
     * points (balance + negative ledger entry), decrements stock, records the
     * redemption. All under one row-locked transaction so concurrent redeems
     * cannot oversell stock or overdraw points.
     *
     * @throws \DomainException reward_inactive | insufficient_points | out_of_stock
     */
    public function redeem(Community $community, CommunityReward $reward, string $profileId): RewardRedemption
    {
        if ($reward->community_id !== $community->id) {
            throw new \InvalidArgumentException('reward_not_in_community');
        }

        return DB::transaction(function () use ($community, $reward, $profileId): RewardRedemption {
            /** @var CommunityReward $reward */
            $reward = CommunityReward::query()->lockForUpdate()->findOrFail($reward->id);

            if (! $reward->is_active) {
                throw new \DomainException('reward_inactive');
            }

            $balance = CommunityPoints::query()
                ->where('community_id', $community->id)
                ->where('profile_id', $profileId)
                ->lockForUpdate()
                ->first();

            $current = (int) ($balance->points ?? 0);

            if ($current < $reward->cost_points) {
                throw new \DomainException('insufficient_points');
            }

            if ($reward->stock !== null && $reward->stock <= 0) {
                throw new \DomainException('out_of_stock');
            }

            // Deduct points: balance + audit ledger entry (negative).
            if ($balance === null) {
                $balance = CommunityPoints::query()->create([
                    'community_id' => $community->id,
                    'profile_id' => $profileId,
                    'points' => 0,
                ]);
            }
            $balance->decrement('points', $reward->cost_points);

            CommunityPointLedger::query()->create([
                'community_id' => $community->id,
                'profile_id' => $profileId,
                'points' => -$reward->cost_points,
                'source' => CommunityPointSource::Redemption,
                'reference_id' => $reward->id,
                'description' => 'Redeemed: '.$reward->title,
            ]);

            if ($reward->stock !== null) {
                $reward->decrement('stock');
            }

            return RewardRedemption::query()->create([
                'community_id' => $community->id,
                'profile_id' => $profileId,
                'reward_id' => $reward->id,
                'points_spent' => $reward->cost_points,
                'status' => RewardRedemptionStatus::Pending,
                'redeemed_at' => now(),
            ]);
        });
    }
}
