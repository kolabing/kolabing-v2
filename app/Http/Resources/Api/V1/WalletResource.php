<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet;
use App\Services\Admin\RewardEconomicsService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 */
class WalletResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $economics = app(RewardEconomicsService::class)->current();
        $threshold = $economics->withdrawalThresholdPoints();
        $referralAvailable = $this->getReferralAvailablePoints();

        return [
            // XP reputation — shown to the user but never cash-convertible.
            'points' => $this->points,
            'redeemed_points' => $this->redeemed_points,
            'available_points' => $this->getAvailablePoints(),
            // Referral rewards — the real, withdrawable cash value.
            'referral_available_points' => $referralAvailable,
            'eur_value' => $economics->payoutFor($referralAvailable),
            'progress' => $threshold > 0 ? round(min($referralAvailable / $threshold, 1.0), 4) : 0.0,
            'can_withdraw' => $referralAvailable >= $threshold && ! $this->pending_withdrawal,
            'pending_withdrawal' => $this->pending_withdrawal,
            'withdrawal_threshold' => $threshold,
        ];
    }
}
