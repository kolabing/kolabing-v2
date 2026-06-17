<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CrmTask>
 */
class CrmTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'area' => fake()->randomElement(['sales', 'marketing', 'dev']),
            'subarea' => fake()->randomElement(['business', 'communities', 'community_members']),
            'status' => fake()->randomElement(['open', 'doing', 'done']),
            'assignee' => fake()->optional()->firstName(),
            'due_on' => fake()->optional()->dateTimeBetween('now', '+30 days'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
