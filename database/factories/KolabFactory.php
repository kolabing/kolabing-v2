<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kolab>
 */
class KolabFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Kolab>
     */
    protected $model = Kolab::class;

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
            'intent_type' => IntentType::CommunitySeeking,
            'status' => KolabStatus::Draft,
            'title' => fake()->sentence(6),
            'description' => fake()->paragraphs(2, true),
            'preferred_city' => fake()->randomElement(['Sevilla', 'Malaga', 'Granada', 'Cordoba', 'Cadiz']),
            'area' => fake()->optional(0.5)->streetName(),
            'media' => null,
            'availability_mode' => 'flexible',
            'availability_start' => $availabilityStart,
            'availability_end' => $availabilityEnd,
            'selected_time' => null,
            'recurring_days' => null,
            'needs' => [
                'social_media_content' => fake()->boolean(80),
                'event_activation' => fake()->boolean(50),
                'product_placement' => fake()->boolean(40),
            ],
            'community_types' => fake()->randomElements(
                ['fitness', 'food', 'travel', 'tech', 'art', 'music', 'wellness'],
                fake()->numberBetween(1, 3)
            ),
            'community_size' => fake()->optional(0.6)->numberBetween(100, 10000),
            'typical_attendance' => fake()->optional(0.5)->numberBetween(10, 500),
            'offers_in_return' => [
                'venue' => fake()->boolean(70),
                'food_drink' => fake()->boolean(50),
                'discount' => fake()->boolean(40),
            ],
            'venue_preference' => fake()->randomElement(['business_provides', 'community_provides', 'no_venue']),
            'venue_name' => null,
            'venue_type' => null,
            'capacity' => null,
            'venue_address' => null,
            'product_name' => null,
            'product_type' => null,
            'offering' => null,
            'seeking_communities' => null,
            'min_community_size' => null,
            'expects' => null,
            'past_events' => null,
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the kolab is a venue promotion.
     */
    public function venuePromotion(): static
    {
        return $this->state(fn (array $attributes) => [
            'intent_type' => IntentType::VenuePromotion,
            'venue_name' => fake()->company(),
            'venue_type' => fake()->randomElement(['restaurant', 'cafe', 'bar_lounge', 'hotel', 'event_space']),
            'capacity' => fake()->numberBetween(20, 500),
            'venue_address' => fake()->address(),
            'offering' => [
                'venue_space' => true,
                'food_drink' => fake()->boolean(70),
                'discount' => fake()->boolean(40),
            ],
            'seeking_communities' => [
                'types' => fake()->randomElements(['fitness', 'food', 'travel', 'tech', 'art'], fake()->numberBetween(1, 3)),
            ],
            'min_community_size' => fake()->numberBetween(50, 1000),
            'expects' => [
                'social_media_content' => true,
                'event_activation' => fake()->boolean(60),
            ],
        ]);
    }

    /**
     * Indicate that the kolab is a product promotion.
     */
    public function productPromotion(): static
    {
        return $this->state(fn (array $attributes) => [
            'intent_type' => IntentType::ProductPromotion,
            'product_name' => fake()->words(3, true),
            'product_type' => fake()->randomElement(['food_product', 'beverage', 'health_beauty', 'fashion']),
            'offering' => [
                'free_samples' => true,
                'discount_code' => fake()->boolean(60),
                'commission' => fake()->boolean(30),
            ],
            'seeking_communities' => [
                'types' => fake()->randomElements(['fitness', 'food', 'travel', 'wellness', 'fashion'], fake()->numberBetween(1, 3)),
            ],
            'min_community_size' => fake()->numberBetween(100, 5000),
            'expects' => [
                'social_media_content' => true,
                'review_feedback' => fake()->boolean(70),
            ],
        ]);
    }

    /**
     * Indicate that the kolab is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KolabStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the kolab is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => KolabStatus::Closed,
            'published_at' => now()->subDays(fake()->numberBetween(5, 30)),
        ]);
    }

    /**
     * Set a specific creator profile.
     */
    public function forCreator(Profile $profile): static
    {
        return $this->state(fn (array $attributes) => [
            'creator_profile_id' => $profile->id,
        ]);
    }
}
