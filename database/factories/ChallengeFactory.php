<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChallengeDifficulty;
use App\Models\Challenge;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Challenge>
 */
class ChallengeFactory extends Factory
{
    protected $model = Challenge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $difficulty = $this->faker->randomElement(ChallengeDifficulty::cases());

        return [
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'difficulty' => $difficulty,
            'points' => $difficulty->points(),
            'is_system' => false,
            'event_id' => null,
        ];
    }

    public function system(): static
    {
        return $this->state(fn (): array => ['is_system' => true, 'event_id' => null]);
    }

    public function easy(): static
    {
        return $this->state(fn (): array => [
            'difficulty' => ChallengeDifficulty::Easy,
            'points' => ChallengeDifficulty::Easy->points(),
        ]);
    }

    public function medium(): static
    {
        return $this->state(fn (): array => [
            'difficulty' => ChallengeDifficulty::Medium,
            'points' => ChallengeDifficulty::Medium->points(),
        ]);
    }

    public function hard(): static
    {
        return $this->state(fn (): array => [
            'difficulty' => ChallengeDifficulty::Hard,
            'points' => ChallengeDifficulty::Hard->points(),
        ]);
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn (): array => ['event_id' => $event->id, 'is_system' => false]);
    }
}
