<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\RewardRedemption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RewardRedemption
 */
class RewardRedemptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'profile_id' => $this->profile_id,
            'reward_id' => $this->reward_id,
            'points_spent' => $this->points_spent,
            'status' => $this->status->value,
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
        ];
    }
}
