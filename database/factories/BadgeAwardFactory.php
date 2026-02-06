<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BadgeAward>
 */
class BadgeAwardFactory extends Factory
{
    protected $model = BadgeAward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'badge_id' => Badge::factory(),
            'profile_id' => Profile::factory()->attendee(),
            'awarded_at' => now(),
        ];
    }
}
