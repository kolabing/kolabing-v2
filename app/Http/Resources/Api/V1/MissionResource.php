<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Challenge;
use App\Models\ChallengeProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A self-tracked mission as seen by a viewer, for `GET /me/missions`.
 *
 * The viewer's progress for the CURRENT period is attached by the controller as
 * `current_progress` (a ChallengeProgress or null) plus the resolved
 * `current_period_key`, so the shape always reflects the active repeat window.
 *
 * @mixin Challenge
 */
class MissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ChallengeProgress|null $progress */
        $progress = $this->resource->current_progress;
        $periodKey = $this->resource->current_period_key;

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category?->value,
            'points' => $this->points,
            'difficulty' => $this->difficulty->value,
            'target_value' => $this->target_value,
            'repeat_interval' => $this->repeat_interval?->value,
            'progress_count' => $progress?->progress_count ?? 0,
            'completed' => $progress?->completed_at !== null,
            'completed_at' => $progress?->completed_at?->toIso8601String(),
            'period_key' => $periodKey,
        ];
    }
}
