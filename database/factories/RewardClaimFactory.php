<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RewardClaimStatus;
use App\Models\EventReward;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RewardClaim>
 */
class RewardClaimFactory extends Factory
{
    protected $model = RewardClaim::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_reward_id' => EventReward::factory(),
            'profile_id' => Profile::factory()->attendee(),
            'challenge_completion_id' => null,
            'status' => RewardClaimStatus::Available,
            'won_at' => now(),
            'redeemed_at' => null,
            'redeem_token' => null,
        ];
    }

    /**
     * Indicate that the reward claim has been redeemed.
     */
    public function redeemed(): static
    {
        return $this->state(fn (): array => [
            'status' => RewardClaimStatus::Redeemed,
            'redeemed_at' => now(),
            'redeem_token' => Str::random(64),
        ]);
    }

    /**
     * Indicate that the reward claim has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => RewardClaimStatus::Expired,
        ]);
    }

    /**
     * Indicate that the reward claim has a redeem token.
     */
    public function withRedeemToken(): static
    {
        return $this->state(fn (): array => [
            'redeem_token' => Str::random(64),
        ]);
    }
}
