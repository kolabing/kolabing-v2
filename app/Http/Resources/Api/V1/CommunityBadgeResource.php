<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityBadge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunityBadge
 *
 * Optionally carries `earned` when the controller sets it (rewards-hub).
 */
class CommunityBadgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'title' => $this->title,
            'icon' => $this->icon,
            'criteria_type' => $this->criteria_type->value,
            'criteria_value' => $this->criteria_value,
            'challenge_ids' => $this->challenge_ids ?? [],
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->resource->offsetExists('earned')) {
            $data['earned'] = (bool) $this->getAttribute('earned');
        }

        return $data;
    }
}
