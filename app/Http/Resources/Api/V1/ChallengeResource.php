<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Challenge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Challenge
 */
class ChallengeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'difficulty' => $this->difficulty->value,
            'points' => $this->points,
            'is_system' => $this->is_system,
            'event_id' => $this->event_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
