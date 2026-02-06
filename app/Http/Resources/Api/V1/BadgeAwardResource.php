<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BadgeAward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BadgeAward
 */
class BadgeAwardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'badge' => new BadgeResource($this->whenLoaded('badge')),
            'awarded_at' => $this->awarded_at?->toIso8601String(),
        ];
    }
}
