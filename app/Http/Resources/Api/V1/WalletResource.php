<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Wallet;
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
        return [
            'points' => $this->points,
            'redeemed_points' => $this->redeemed_points,
            'available_points' => $this->getAvailablePoints(),
            'eur_value' => $this->getEurValue(),
            'progress' => $this->getProgress(),
            'can_withdraw' => $this->canWithdraw(),
            'pending_withdrawal' => $this->pending_withdrawal,
            'withdrawal_threshold' => 375,
        ];
    }
}
