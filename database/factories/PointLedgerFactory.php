<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PointEventType;
use App\Models\PointLedger;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PointLedger>
 */
class PointLedgerFactory extends Factory
{
    protected $model = PointLedger::class;

    public function definition(): array
    {
        $eventType = fake()->randomElement([
            PointEventType::CollaborationComplete,
            PointEventType::ReviewPosted,
            PointEventType::UgcPosted,
        ]);

        return [
            'profile_id' => Profile::factory(),
            'points' => $eventType->defaultPoints(),
            'event_type' => $eventType,
            'reference_id' => null,
            'description' => fake()->sentence(),
        ];
    }

    public function collaborationComplete(): static
    {
        return $this->state(fn () => [
            'points' => 1,
            'event_type' => PointEventType::CollaborationComplete,
        ]);
    }

    public function reviewPosted(): static
    {
        return $this->state(fn () => [
            'points' => 1,
            'event_type' => PointEventType::ReviewPosted,
        ]);
    }

    public function ugcPosted(): static
    {
        return $this->state(fn () => [
            'points' => 1,
            'event_type' => PointEventType::UgcPosted,
        ]);
    }

    public function referralConversion(): static
    {
        return $this->state(fn () => [
            'points' => 50,
            'event_type' => PointEventType::ReferralConversion,
        ]);
    }

    public function withdrawal(int $points = 375): static
    {
        return $this->state(fn () => [
            'points' => -$points,
            'event_type' => PointEventType::Withdrawal,
        ]);
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn () => [
            'profile_id' => $profile->id,
        ]);
    }
}
