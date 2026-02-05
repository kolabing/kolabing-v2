<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventCheckin>
 */
class EventCheckinFactory extends Factory
{
    protected $model = EventCheckin::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'profile_id' => Profile::factory()->attendee(),
            'checked_in_at' => now(),
        ];
    }

    /**
     * Set the event for this checkin.
     */
    public function forEvent(Event $event): static
    {
        return $this->state(fn (): array => ['event_id' => $event->id]);
    }

    /**
     * Set the profile for this checkin.
     */
    public function forProfile(Profile $profile): static
    {
        return $this->state(fn (): array => ['profile_id' => $profile->id]);
    }
}
