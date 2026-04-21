<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Http\Requests\Api\V1\BusinessOnboardingRequest;
use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusinessProfile>
 */
class BusinessProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<BusinessProfile>
     */
    protected $model = BusinessProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->business(),
            'name' => fake()->company(),
            'about' => fake()->optional()->paragraph(),
            'business_type' => fake()->randomElement(BusinessOnboardingRequest::BUSINESS_TYPES),
            'city_id' => City::factory(),
            'city_name' => fake()->city(),
            'city_country' => fake()->country(),
            'instagram' => fake()->optional()->userName(),
            'website' => fake()->optional()->url(),
            'profile_photo' => fake()->optional()->imageUrl(400, 400, 'business'),
            'primary_venue' => null,
        ];
    }

    /**
     * Indicate that the business profile is incomplete (no onboarding).
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'about' => null,
            'business_type' => null,
            'city_id' => null,
            'city_name' => null,
            'city_country' => null,
            'instagram' => null,
            'website' => null,
            'profile_photo' => null,
            'primary_venue' => null,
        ]);
    }
}
