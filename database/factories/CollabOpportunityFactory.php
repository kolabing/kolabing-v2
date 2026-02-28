<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OfferStatus;
use App\Enums\UserType;
use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollabOpportunity>
 */
class CollabOpportunityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CollabOpportunity>
     */
    protected $model = CollabOpportunity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $availabilityStart = fake()->dateTimeBetween('+1 week', '+3 months');
        $availabilityEnd = fake()->dateTimeBetween($availabilityStart, '+6 months');

        return [
            'creator_profile_id' => Profile::factory()->business(),
            'creator_profile_type' => UserType::Business,
            'title' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'status' => OfferStatus::Draft,
            'business_offer' => [
                'venue' => fake()->boolean(70),
                'food_drink' => fake()->boolean(50),
                'discount' => [
                    'enabled' => fake()->boolean(40),
                    'percentage' => fake()->randomElement([10, 15, 20, 25, 30]),
                ],
            ],
            'community_deliverables' => [
                'social_media_content' => fake()->boolean(80),
                'event_activation' => fake()->boolean(50),
                'product_placement' => fake()->boolean(40),
                'community_reach' => fake()->boolean(30),
                'review_feedback' => fake()->boolean(60),
                'other' => null,
            ],
            'categories' => fake()->randomElements(
                ['Food & Drink', 'Sports', 'Wellness', 'Music', 'Art', 'Fashion', 'Technology', 'Travel'],
                fake()->numberBetween(1, 3)
            ),
            'availability_mode' => fake()->randomElement(['one_time', 'recurring', 'flexible']),
            'availability_start' => $availabilityStart,
            'availability_end' => $availabilityEnd,
            'venue_mode' => fake()->randomElement(['business_venue', 'community_venue', 'no_venue']),
            'address' => fake()->optional(0.7)->address(),
            'preferred_city' => fake()->randomElement(['Sevilla', 'Malaga', 'Granada', 'Cordoba', 'Cadiz']),
            'offer_photo' => fake()->optional(0.5)->imageUrl(800, 600, 'business'),
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the opportunity is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the opportunity is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Closed,
            'published_at' => now()->subDays(fake()->numberBetween(5, 30)),
        ]);
    }

    /**
     * Indicate that the opportunity is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OfferStatus::Completed,
            'published_at' => now()->subDays(fake()->numberBetween(30, 60)),
        ]);
    }

    /**
     * Indicate that the opportunity was created by a community user.
     */
    public function byCommunity(): static
    {
        return $this->state(fn (array $attributes) => [
            'creator_profile_id' => Profile::factory()->community(),
            'creator_profile_type' => UserType::Community,
        ]);
    }

    /**
     * Set a specific creator profile.
     */
    public function forCreator(Profile $profile): static
    {
        return $this->state(fn (array $attributes) => [
            'creator_profile_id' => $profile->id,
            'creator_profile_type' => $profile->user_type,
        ]);
    }
}
