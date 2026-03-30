<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WithdrawalStatus;
use App\Models\Profile;
use App\Models\WithdrawalRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WithdrawalRequest>
 */
class WithdrawalRequestFactory extends Factory
{
    protected $model = WithdrawalRequest::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'points' => 375,
            'eur_amount' => 75.00,
            'iban' => 'ES7921000813610123456789',
            'account_holder' => fake()->company(),
            'status' => WithdrawalStatus::Pending,
            'notes' => null,
        ];
    }

    public function forProfile(Profile $profile): static
    {
        return $this->state(fn () => [
            'profile_id' => $profile->id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => WithdrawalStatus::Pending,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => WithdrawalStatus::Completed,
        ]);
    }
}
