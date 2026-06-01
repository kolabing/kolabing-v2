<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\RewardEconomics;
use Illuminate\Database\Seeder;

/**
 * Seed the single reward_economics row. €0.20/point matches the current
 * GamificationController::withdrawal() literals (375 points × €0.20 = €75),
 * so deploying this PR with the seed run does NOT change live payouts.
 * Maintainers can edit afterwards. Idempotent: first row is upserted by id.
 */
class RewardEconomicsSeeder extends Seeder
{
    public function run(): void
    {
        if (RewardEconomics::query()->exists()) {
            return;
        }

        RewardEconomics::query()->create([
            'referral_goal' => 3,
            'referral_cash_reward_cents' => 7500,
            'euro_cents_per_point' => 20,
            'withdrawal_threshold_cents' => 7500,
            'currency' => 'EUR',
        ]);
    }
}
