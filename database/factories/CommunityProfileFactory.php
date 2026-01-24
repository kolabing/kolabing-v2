<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Http\Requests\Api\V1\CommunityOnboardingRequest;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommunityProfile>
 */
class CommunityProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CommunityProfile>
     */
    protected $model = CommunityProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->community(),
            'name' => fake()->name(),
            'about' => fake()->optional()->paragraph(),
            'community_type' => fake()->randomElement(CommunityOnboardingRequest::COMMUNITY_TYPES),
            'city_id' => City::factory(),
            'instagram' => fake()->optional()->userName(),
            'tiktok' => fake()->optional()->userName(),
            'website' => fake()->optional()->url(),
            'profile_photo' => fake()->optional()->imageUrl(400, 400, 'people'),
            'is_featured' => false,
        ];
    }

    /**
     * Indicate that the community profile is incomplete (no onboarding).
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'about' => null,
            'community_type' => null,
            'city_id' => null,
            'instagram' => null,
            'tiktok' => null,
            'website' => null,
            'profile_photo' => null,
        ]);
    }

    /**
     * Indicate that the community profile is featured.
     */
    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }
}
