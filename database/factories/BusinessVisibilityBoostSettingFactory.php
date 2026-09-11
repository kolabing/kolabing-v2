<?php

declare(strict_types=1);

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\BusinessVisibilityBoostSetting>
 */
class BusinessVisibilityBoostSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trusted_partner_points' => 5,
            'community_favourite_points' => 10,
        ];
    }
}
