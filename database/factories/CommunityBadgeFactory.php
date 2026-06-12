<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommunityBadgeCriteriaType;
use App\Models\Community;
use App\Models\CommunityBadge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityBadge>
 */
class CommunityBadgeFactory extends Factory
{
    protected $model = CommunityBadge::class;

    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'title' => $this->faker->words(2, true),
            'icon' => 'star',
            'criteria_type' => CommunityBadgeCriteriaType::PointsThreshold->value,
            'criteria_value' => 100,
            'challenge_ids' => null,
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
