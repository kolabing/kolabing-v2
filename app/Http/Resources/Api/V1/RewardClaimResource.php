<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\RewardClaim;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RewardClaim
 */
class RewardClaimResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_reward' => new EventRewardResource($this->whenLoaded('eventReward')),
            'profile_id' => $this->profile_id,
            'status' => $this->status->value,
            'won_at' => $this->won_at->toIso8601String(),
            'redeemed_at' => $this->redeemed_at?->toIso8601String(),
            'redeem_token' => $this->redeem_token,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
