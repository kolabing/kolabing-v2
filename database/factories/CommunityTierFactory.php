<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TierAssignmentRule;
use App\Models\Community;
use App\Models\CommunityTier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityTier>
 */
class CommunityTierFactory extends Factory
{
    protected $model = CommunityTier::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'name' => fake()->word(),
            'rank' => 1,
            'color' => null,
            'assignment_rule' => TierAssignmentRule::Manual->value,
            'threshold' => null,
            'permissions' => ['view' => [], 'chat_channels' => [], 'perks' => [], 'capabilities' => []],
            'is_default' => false,
        ];
    }

    public function forCommunity(Community $community): static
    {
        return $this->state(fn (array $attributes) => [
            'community_id' => $community->id,
        ]);
    }

    public function defaultTier(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Member',
            'rank' => 1,
            'assignment_rule' => TierAssignmentRule::Manual->value,
            'is_default' => true,
        ]);
    }

    public function xpThreshold(int $threshold): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_rule' => TierAssignmentRule::XpThreshold->value,
            'threshold' => $threshold,
        ]);
    }

    public function tenure(int $days): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_rule' => TierAssignmentRule::Tenure->value,
            'threshold' => $days,
        ]);
    }

    public function eventsAttended(int $count): static
    {
        return $this->state(fn (array $attributes) => [
            'assignment_rule' => TierAssignmentRule::EventsAttended->value,
            'threshold' => $count,
        ]);
    }
}
