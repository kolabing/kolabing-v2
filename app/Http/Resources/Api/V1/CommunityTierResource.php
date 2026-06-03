<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityTier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunityTier
 */
class CommunityTierResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $permissions = $this->permissions ?? [];

        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'name' => $this->name,
            'rank' => $this->rank,
            'color' => $this->color,
            'assignment_rule' => $this->assignment_rule->value,
            'threshold' => $this->threshold,
            'permissions' => [
                'view' => $permissions['view'] ?? [],
                'chat_channels' => $permissions['chat_channels'] ?? [],
                'perks' => $permissions['perks'] ?? [],
                'capabilities' => $permissions['capabilities'] ?? [],
            ],
            'is_default' => $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
