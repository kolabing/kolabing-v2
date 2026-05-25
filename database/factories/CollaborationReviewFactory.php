<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollaborationReview>
 */
class CollaborationReviewFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CollaborationReview>
     */
    protected $model = CollaborationReview::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collaboration_id' => Collaboration::factory(),
            'reviewer_profile_id' => Profile::factory(),
            'reviewed_profile_id' => Profile::factory(),
            'reviewer_role' => fake()->randomElement(['creator', 'applicant']),
            'rating' => fake()->numberBetween(1, 5),
            'note' => fake()->optional()->sentence(),
            'body' => fake()->optional()->paragraph(),
            'would_collaborate_again' => fake()->optional()->boolean(),
        ];
    }
}
