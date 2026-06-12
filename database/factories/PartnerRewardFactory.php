<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PartnerReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerReward>
 */
class PartnerRewardFactory extends Factory
{
    protected $model = PartnerReward::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->words(2, true),
            'partner' => $this->faker->company(),
            'description' => $this->faker->sentence(),
            'image_url' => null,
            'cost_xp' => 500,
            'is_active' => true,
        ];
    }
}
