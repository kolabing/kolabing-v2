<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventReward;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventReward>
 */
class EventRewardFactory extends Factory
{
    protected $model = EventReward::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $totalQuantity = $this->faker->numberBetween(10, 100);

        return [
            'event_id' => Event::factory(),
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'total_quantity' => $totalQuantity,
            'remaining_quantity' => $totalQuantity,
            'probability' => $this->faker->randomFloat(4, 0.01, 1.0),
            'expires_at' => null,
        ];
    }

    public function forEvent(Event $event): static
    {
        return $this->state(fn (): array => ['event_id' => $event->id]);
    }

    public function outOfStock(): static
    {
        return $this->state(fn (): array => ['remaining_quantity' => 0]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->subDay()]);
    }

    public function withExpiry(): static
    {
        return $this->state(fn (): array => ['expires_at' => now()->addWeek()]);
    }

    public function highProbability(float $probability = 0.8): static
    {
        return $this->state(fn (): array => ['probability' => $probability]);
    }

    public function lowProbability(float $probability = 0.05): static
    {
        return $this->state(fn (): array => ['probability' => $probability]);
    }
}
