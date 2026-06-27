<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\DTOs\MissionWithProgress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A self-tracked mission as seen by a viewer, for `GET /me/missions`.
 *
 * @mixin MissionWithProgress
 */
class MissionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MissionWithProgress $dto */
        $dto = $this->resource;
        $mission = $dto->mission;
        $progress = $dto->progress;

        return [
            'id' => $mission->id,
            'slug' => $mission->slug,
            'name' => $mission->name,
            'description' => $mission->description,
            'category' => $mission->category?->value,
            'points' => $mission->points,
            'difficulty' => $mission->difficulty->value,
            'target_value' => $mission->target_value,
            'repeat_interval' => $mission->repeat_interval?->value,
            'progress_count' => $progress?->progress_count ?? 0,
            'completed' => $progress?->completed_at !== null,
            'completed_at' => $progress?->completed_at?->toIso8601String(),
            'period_key' => $dto->periodKey,
        ];
    }
}
