<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Encounter;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Encounter>
 */
class EncounterFactory extends Factory
{
    protected $model = Encounter::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->attendee(),
            'other_profile_id' => Profile::factory()->attendee(),
            'event_id' => Event::factory(),
            'met_at' => now(),
            'times_met' => 1,
        ];
    }

    /**
     * Someone met at an event who does not have the app yet.
     */
    public function ghost(string $name = 'Ana'): self
    {
        return $this->state(fn (): array => [
            'other_profile_id' => null,
            'ghost_name' => $name,
        ]);
    }

    /**
     * The nth meeting for this pair.
     */
    public function timesMet(int $times): self
    {
        return $this->state(fn (): array => ['times_met' => $times]);
    }
}
