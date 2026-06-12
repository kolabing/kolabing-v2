<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityGoal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunityGoal
 *
 * Optionally carries viewer progress when the controller sets `progress` /
 * `completed` on the model via setAttribute (rewards-hub).
 */
class CommunityGoalResource extends JsonResource
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
            'earn_type' => $this->earn_type->value,
            'target' => $this->target,
            'reward_points' => $this->reward_points,
            'challenge_id' => $this->challenge_id,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->resource->offsetExists('progress')) {
            $data['progress'] = (int) $this->getAttribute('progress');
            $data['completed'] = (bool) $this->getAttribute('completed');
        }

        return $data;
    }
}
