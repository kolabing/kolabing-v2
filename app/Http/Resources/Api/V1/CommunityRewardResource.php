<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityReward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunityReward
 *
 * Optionally carries `affordable` when the controller sets it (rewards-hub /
 * me-rewards-overview).
 */
class CommunityRewardResource extends JsonResource
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
            'description' => $this->description,
            'cost_points' => $this->cost_points,
            'stock' => $this->stock,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->resource->offsetExists('affordable')) {
            $data['affordable'] = (bool) $this->getAttribute('affordable');
        }

        return $data;
    }
}
