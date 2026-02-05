<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChallengeCompletionStatus;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChallengeCompletion>
 */
class ChallengeCompletionFactory extends Factory
{
    protected $model = ChallengeCompletion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'challenge_id' => Challenge::factory(),
            'event_id' => Event::factory(),
            'challenger_profile_id' => Profile::factory()->attendee(),
            'verifier_profile_id' => Profile::factory()->attendee(),
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
            'completed_at' => null,
        ];
    }

    /**
     * Indicate that the challenge completion has been verified.
     */
    public function verified(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeCompletionStatus::Verified,
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the challenge completion has been rejected.
     */
    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => ChallengeCompletionStatus::Rejected,
        ]);
    }
}
