<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\ChallengeDifficulty;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
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
            'category' => $this->faker->randomElement(ChallengeCategory::cases()),
            'event_id' => null,
            'slug' => null,
            'trigger_action' => null,
            'target_value' => 1,
            'repeat_interval' => MissionRepeat::Once,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * A self-tracked mission row (trigger + target + repeat).
     */
    public function mission(
        ?MissionTrigger $trigger = null,
        int $targetValue = 1,
        MissionRepeat $repeat = MissionRepeat::Once,
        ChallengeAudience $audience = ChallengeAudience::Attendee,
    ): static {
        return $this->state(fn (): array => [
            'is_system' => true,
            'event_id' => null,
            'audience' => $audience,
            'trigger_action' => $trigger ?? $this->faker->randomElement(MissionTrigger::cases()),
            'target_value' => $targetValue,
            'repeat_interval' => $repeat,
            'slug' => 'mission-'.$this->faker->unique()->slug(3),
        ]);
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
