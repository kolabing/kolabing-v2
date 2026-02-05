<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AttendeeProfile;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendeeProfile>
 */
class AttendeeProfileFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AttendeeProfile>
     */
    protected $model = AttendeeProfile::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory()->attendee(),
            'total_points' => 0,
            'total_challenges_completed' => 0,
            'total_events_attended' => 0,
        ];
    }
}
