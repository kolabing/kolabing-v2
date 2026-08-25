<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Community;
use App\Models\EventSeries;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventSeries>
 */
class EventSeriesFactory extends Factory
{
    protected $model = EventSeries::class;

    /**
     * `byweekday` is stored in the `Carbon::dayOfWeek` convention — 0 = Sunday
     * .. 6 = Saturday — not the ISO one `Kolab.recurring_days` uses. The default
     * is Tuesday.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'community_id' => Community::factory(),
            'profile_id' => Profile::factory()->community(),
            'name' => fake()->sentence(3),
            'frequency' => 'weekly',
            'byweekday' => [2],
            'time_of_day' => '19:00',
            'duration_minutes' => 90,
            'ends_mode' => 'never',
            'starts_on' => now()->subMonths(2)->toDateString(),
        ];
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn (): array => [
            'profile_id' => $profile->id,
            'community_id' => Community::factory()->forOwner($profile),
        ]);
    }

    /**
     * @param  array<int, int>  $weekdays  0 = Sunday .. 6 = Saturday
     */
    public function onWeekdays(array $weekdays): static
    {
        return $this->state(fn (): array => [
            'byweekday' => $weekdays,
        ]);
    }

    /**
     * A rule that has already run out: only `ends_mode` "until" can expire on a
     * date, which is what makes a series inactive.
     */
    public function ended(): static
    {
        return $this->state(fn (): array => [
            'ends_mode' => 'until',
            'ends_on' => now()->subMonth()->toDateString(),
        ]);
    }
}
