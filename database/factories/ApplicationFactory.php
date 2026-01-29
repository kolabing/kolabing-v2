<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Application>
     */
    protected $model = Application::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'collab_opportunity_id' => CollabOpportunity::factory()->published(),
            'applicant_profile_id' => Profile::factory()->community(),
            'applicant_profile_type' => UserType::Community,
            'message' => fake()->paragraphs(2, true),
            'availability' => fake()->sentence(10),
            'status' => ApplicationStatus::Pending,
        ];
    }

    /**
     * Indicate that the application is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Pending,
        ]);
    }

    /**
     * Indicate that the application is accepted.
     */
    public function accepted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Accepted,
        ]);
    }

    /**
     * Indicate that the application is declined.
     */
    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Declined,
        ]);
    }

    /**
     * Indicate that the application is withdrawn.
     */
    public function withdrawn(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ApplicationStatus::Withdrawn,
        ]);
    }

    /**
     * Set the opportunity for this application.
     */
    public function forOpportunity(CollabOpportunity $opportunity): static
    {
        return $this->state(fn (array $attributes) => [
            'collab_opportunity_id' => $opportunity->id,
        ]);
    }

    /**
     * Set the applicant profile for this application.
     */
    public function forApplicant(Profile $applicant): static
    {
        return $this->state(fn (array $attributes) => [
            'applicant_profile_id' => $applicant->id,
            'applicant_profile_type' => $applicant->user_type,
        ]);
    }

    /**
     * Create an application from a business user.
     */
    public function fromBusiness(): static
    {
        return $this->state(fn (array $attributes) => [
            'applicant_profile_id' => Profile::factory()->business(),
            'applicant_profile_type' => UserType::Business,
        ]);
    }
}
