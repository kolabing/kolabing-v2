<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Community;
use App\Models\CommunityReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityReward>
 */
class CommunityRewardFactory extends Factory
{
    protected $model = CommunityReward::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'title' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'cost_points' => 50,
            'stock' => null,
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
