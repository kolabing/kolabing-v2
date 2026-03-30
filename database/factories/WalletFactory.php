<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Wallet>
 */
class WalletFactory extends Factory
{
    protected $model = Wallet::class;

    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'points' => 0,
            'redeemed_points' => 0,
            'pending_withdrawal' => false,
        ];
    }

    public function withPoints(int $points): static
    {
        return $this->state(fn () => [
            'points' => $points,
        ]);
    }

    public function withdrawable(): static
    {
        return $this->state(fn () => [
            'points' => 375,
            'redeemed_points' => 0,
            'pending_withdrawal' => false,
        ]);
    }

    public function pendingWithdrawal(): static
    {
        return $this->state(fn () => [
            'pending_withdrawal' => true,
        ]);
    }
}
