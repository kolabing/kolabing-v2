<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GamificationBadgeSlug;
use App\Models\EarnedBadge;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EarnedBadge>
 */
class EarnedBadgeFactory extends Factory
{
    protected $model = EarnedBadge::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'badge_slug' => fake()->randomElement(GamificationBadgeSlug::cases()),
            'earned_at' => now(),
        ];
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn () => [
            'profile_id' => $profile->id,
        ]);
    }

    public function slug(GamificationBadgeSlug $slug): static
    {
        return $this->state(fn () => [
            'badge_slug' => $slug,
        ]);
    }
}
