<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventVisibility;
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
            'partner_name' => $this->faker->company(),
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
     * A public event any attendee may discover + RSVP to (Batch 3).
     */
    public function publicVisibility(): static
    {
        return $this->state(fn (): array => [
            'visibility' => EventVisibility::Public->value,
        ]);
    }

    /**
     * A members-only event (the default) — RSVP requires active membership.
     */
    public function membersOnly(): static
    {
        return $this->state(fn (): array => [
            'visibility' => EventVisibility::MembersOnly->value,
        ]);
    }

    /**
     * An upcoming occurrence (drives sign-up / RSVP and the discovery feed).
     */
    public function upcoming(): static
    {
        return $this->state(fn (): array => [
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
        ]);
    }
}
