<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommunityGoalEarnType;
use App\Models\Community;
use App\Models\CommunityGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityGoal>
 */
class CommunityGoalFactory extends Factory
{
    protected $model = CommunityGoal::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'title' => $this->faker->sentence(3),
            'earn_type' => CommunityGoalEarnType::EventCheckIns->value,
            'target' => 1,
            'reward_points' => 25,
            'challenge_id' => null,
            'is_active' => true,
        ];
    }

    public function forCommunity(Community $community): static
    {
        return $this->state(fn (array $attributes) => [
            'community_id' => $community->id,
        ]);
    }
}
