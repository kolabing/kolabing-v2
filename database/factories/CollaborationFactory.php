<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CollaborationStatus;
use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Collaboration>
 */
class CollaborationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Collaboration>
     */
    protected $model = Collaboration::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $creator = Profile::factory()->business();
        $applicant = Profile::factory()->community();

        return [
            'application_id' => Application::factory(),
            'collab_opportunity_id' => CollabOpportunity::factory()->published(),
            'creator_profile_id' => $creator,
            'applicant_profile_id' => $applicant,
            'business_profile_id' => null,
            'community_profile_id' => null,
            'status' => CollaborationStatus::Scheduled,
            'scheduled_date' => fake()->dateTimeBetween('+1 day', '+3 months'),
            'completed_at' => null,
            'contact_methods' => null,
        ];
    }

    /**
     * Set the collaboration as scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CollaborationStatus::Scheduled,
        ]);
    }

    /**
     * Set the collaboration as active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CollaborationStatus::Active,
        ]);
    }

    /**
     * Set the collaboration as completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CollaborationStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Set the collaboration as cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CollaborationStatus::Cancelled,
        ]);
    }

    /**
     * Set a specific scheduled date.
     */
    public function scheduledOn(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'scheduled_date' => $date,
        ]);
    }

    /**
     * Set the creator profile.
     */
    public function forCreator(Profile $creator): static
    {
        return $this->state(fn (array $attributes) => [
            'creator_profile_id' => $creator->id,
        ]);
    }

    /**
     * Set the applicant profile.
     */
    public function forApplicant(Profile $applicant): static
    {
        return $this->state(fn (array $attributes) => [
            'applicant_profile_id' => $applicant->id,
        ]);
    }

    /**
     * Set the opportunity.
     */
    public function forOpportunity(CollabOpportunity $opportunity): static
    {
        return $this->state(fn (array $attributes) => [
            'collab_opportunity_id' => $opportunity->id,
        ]);
    }

    /**
     * Set the application.
     */
    public function forApplication(Application $application): static
    {
        return $this->state(fn (array $attributes) => [
            'application_id' => $application->id,
        ]);
    }
}
