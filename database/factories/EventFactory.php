<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\UserType;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'name' => $this->faker->sentence(3),
            'partner_id' => Profile::factory(),
            'partner_type' => $this->faker->randomElement([UserType::Business->value, UserType::Community->value]),
            'event_date' => $this->faker->dateTimeBetween('-2 years', '-1 day')->format('Y-m-d'),
            'attendee_count' => $this->faker->numberBetween(1, 5000),
        ];
    }

    /**
     * Set the profile for this event.
     */
    public function forProfile(Profile $profile): static
    {
        return $this->state(fn (): array => [
            'profile_id' => $profile->id,
        ]);
    }

    /**
     * Set the partner for this event.
     */
    public function withPartner(Profile $partner): static
    {
        return $this->state(fn (): array => [
            'partner_id' => $partner->id,
            'partner_type' => $partner->user_type->value,
        ]);
    }
}
