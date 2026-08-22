<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityInvitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunityInvitation
 */
class CommunityInvitationResource extends JsonResource
{
    /**
     * The token is deliberately absent: it is a bearer credential and belongs
     * only in the invitation email.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'email' => $this->email,
            'tier_id' => $this->tier_id,
            'tier' => $this->whenLoaded('tier', fn () => $this->tier
                ? new CommunityTierResource($this->tier)
                : null),
            'status' => $this->status->value,
            'is_claimable' => $this->isClaimable(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'accepted_at' => $this->accepted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
