<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row settings model for the community reward economy.
 *
 * @property string $id
 * @property int $referral_goal
 * @property int $referral_cash_reward_cents
 * @property int $euro_cents_per_point
 * @property int $withdrawal_threshold_cents
 * @property string $currency
 */
class RewardEconomics extends Model
{
    /** @use HasFactory<\Database\Factories\RewardEconomicsFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = 'reward_economics';

    /** @var list<string> */
    protected $fillable = [
        'referral_goal',
        'referral_cash_reward_cents',
        'euro_cents_per_point',
        'withdrawal_threshold_cents',
        'currency',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'referral_goal' => 'integer',
            'referral_cash_reward_cents' => 'integer',
            'euro_cents_per_point' => 'integer',
            'withdrawal_threshold_cents' => 'integer',
        ];
    }

    /**
     * Number of points required to reach the withdrawal threshold.
     * Always rounded up so a marginally underpaid user can't withdraw.
     */
    public function withdrawalThresholdPoints(): int
    {
        if ($this->euro_cents_per_point <= 0) {
            return 0;
        }

        return (int) ceil($this->withdrawal_threshold_cents / $this->euro_cents_per_point);
    }

    /**
     * Payout for a withdrawal of N points, in euros (2dp).
     */
    public function payoutFor(int $points): float
    {
        return round(($points * $this->euro_cents_per_point) / 100, 2);
    }
}
