<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChallengeProgress>
 */
class ChallengeProgressFactory extends Factory
{
    protected $model = ChallengeProgress::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'challenge_id' => Challenge::factory(),
            'profile_id' => Profile::factory(),
            'progress_count' => 0,
            'target_value' => 1,
            'completed_at' => null,
            'period_key' => 'once',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'progress_count' => 1,
            'completed_at' => now(),
        ]);
    }
}
