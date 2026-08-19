<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabEventStatus;
use App\Models\MultiKolabEvent;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MultiKolabEvent>
 */
class MultiKolabEventFactory extends Factory
{
    /**
     * @var class-string<MultiKolabEvent>
     */
    protected $model = MultiKolabEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'creator_profile_id' => Profile::factory()->business(),
            'status' => MultiKolabEventStatus::Draft,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'value_summary' => fake()->sentence(),
            'venue_needed' => fake()->boolean(),
            'date_mode' => 'exact',
            'event_date' => fake()->dateTimeBetween('+1 week', '+3 months'),
            'date_range_start' => null,
            'date_range_end' => null,
            'city' => fake()->city(),
            'category' => fake()->randomElement(['Music', 'Sports', 'Food & Drink', 'Wellness']),
            'rsvp_url' => null,
            'eligible_account_type' => MultiKolabEligibleAccountType::Either,
            'published_at' => null,
        ];
    }

    public function recruiting(): static
    {
        return $this->state(fn (): array => [
            'status' => MultiKolabEventStatus::Recruiting,
            'published_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => MultiKolabEventStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(),
        ]);
    }
}
