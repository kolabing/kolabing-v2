<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Collaboration;
use App\Models\CollaborationFeedback;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollaborationFeedback>
 */
class CollaborationFeedbackFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<CollaborationFeedback>
     */
    protected $model = CollaborationFeedback::class;

    /**
     * Define the model's default state.
     *
     * Defaults to a business reviewer; use the community() state for the
     * other side.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collaboration_id' => Collaboration::factory(),
            'reviewer_profile_id' => Profile::factory(),
            'reviewer_type' => 'business',
            'reviewer_role' => fake()->randomElement(['creator', 'applicant']),
            'rating' => fake()->numberBetween(1, 5),
            'posts_reels' => fake()->numberBetween(0, 20),
            'expectation_match' => fake()->boolean(),
            'would_recommend' => fake()->boolean(),
            'would_collaborate_again' => fake()->boolean(),
            'stories_posted' => fake()->numberBetween(0, 20),
            'revenue' => fake()->randomFloat(2, 0, 5000),
            'benefits' => null,
        ];
    }

    /**
     * Business-side feedback: revenue + stories_posted populated, benefits null.
     */
    public function business(): self
    {
        return $this->state(fn (): array => [
            'reviewer_type' => 'business',
            'stories_posted' => fake()->numberBetween(0, 20),
            'revenue' => fake()->randomFloat(2, 0, 5000),
            'benefits' => null,
        ]);
    }

    /**
     * Community-side feedback: benefits populated, business-only fields null.
     */
    public function community(): self
    {
        return $this->state(fn (): array => [
            'reviewer_type' => 'community',
            'stories_posted' => null,
            'revenue' => null,
            'benefits' => fake()->sentence(),
        ]);
    }
}
