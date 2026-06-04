<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Community
 */
class CommunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'owner_profile_id' => $this->owner_profile_id,
            'community_profile_id' => $this->community_profile_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type->value,
            'description' => $this->description,
            'avatar_url' => $this->avatar_url,
            'is_primary' => $this->is_primary,
            'join_policy' => $this->join_policy->value,
            'member_count' => $this->memberCount(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
