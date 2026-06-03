<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Models\RewardEconomics;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;

/**
 * Read + write the single reward_economics row. Reads cached so the
 * withdrawal flow (called per user action) doesn't hit the DB each time.
 * Cache busted on admin write.
 */
class RewardEconomicsService
{
    public const CACHE_KEY = 'reward_economics.current';

    /**
     * Return the current settings row (cached). Falls back to a zero-state
     * defaults object if no row exists, so the withdrawal flow never crashes.
     */
    public function current(): RewardEconomics
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addHour(),
            fn (): RewardEconomics => RewardEconomics::query()->first() ?? new RewardEconomics([
                'referral_goal' => 3,
                'referral_cash_reward_cents' => 7500,
                'euro_cents_per_point' => 20,
                'withdrawal_threshold_cents' => 7500,
                'currency' => 'EUR',
            ]),
        );
    }

    /**
     * Update the single row, bust the cache, return the fresh state.
     *
     * @param  array<string, int|string>  $data
     */
    public function update(array $data): RewardEconomics
    {
        $row = RewardEconomics::query()->first();

        if ($row === null) {
            $row = RewardEconomics::query()->create($data);
        } else {
            $row->fill($data);
            $row->save();
        }

        Cache::forget(self::CACHE_KEY);

        return $row->fresh();
    }

    /**
     * Cost-impact preview for the admin form. Reports how many community
     * wallets would qualify for a withdrawal at the given rate + threshold,
     * and the total euro liability if every one of them cashed out.
     *
     * Use this to render the live preview card on /admin/gamification/economics
     * — pass current values for "now" and the form's current input for "after".
     *
     * @return array{
     *     threshold_points: int,
     *     eligible_wallets: int,
     *     total_payout_euros: float,
     *     per_point_euros: float,
     * }
     */
    public function costImpact(int $euroCentsPerPoint, int $withdrawalThresholdCents): array
    {
        $thresholdPoints = $euroCentsPerPoint > 0
            ? (int) ceil($withdrawalThresholdCents / $euroCentsPerPoint)
            : 0;

        // Available points = points - redeemed_points; eligible if >= threshold.
        $eligibleWallets = Wallet::query()
            ->whereRaw('(points - redeemed_points) >= ?', [$thresholdPoints])
            ->count();

        $totalPayoutEuros = round(
            ($eligibleWallets * $thresholdPoints * $euroCentsPerPoint) / 100,
            2,
        );

        return [
            'threshold_points' => $thresholdPoints,
            'eligible_wallets' => $eligibleWallets,
            'total_payout_euros' => $totalPayoutEuros,
            'per_point_euros' => round($euroCentsPerPoint / 100, 4),
        ];
    }
}
