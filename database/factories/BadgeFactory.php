<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BadgeMilestoneType;
use App\Models\Badge;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Badge>
 */
class BadgeFactory extends Factory
{
    protected $model = Badge::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'icon' => 'badge-default',
            'milestone_type' => BadgeMilestoneType::FirstCheckin,
            'milestone_value' => 1,
        ];
    }

    public function forMilestone(BadgeMilestoneType $type, int $value): static
    {
        return $this->state([
            'milestone_type' => $type,
            'milestone_value' => $value,
        ]);
    }
}
