<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CollaborationCompletionStatus;
use App\Models\Collaboration;
use App\Models\CollaborationCompletion;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CollaborationCompletion>
 */
class CollaborationCompletionFactory extends Factory
{
    /**
     * @var class-string<CollaborationCompletion>
     */
    protected $model = CollaborationCompletion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collaboration_id' => Collaboration::factory(),
            'profile_id' => Profile::factory(),
            'role' => fake()->randomElement(['creator', 'applicant']),
            'status' => CollaborationCompletionStatus::Yes,
            'note' => null,
        ];
    }

    public function no(): self
    {
        return $this->state(fn (): array => ['status' => CollaborationCompletionStatus::No]);
    }

    public function notYet(): self
    {
        return $this->state(fn (): array => ['status' => CollaborationCompletionStatus::NotYet]);
    }
}
