<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Badge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Badge
 */
class BadgeResource extends JsonResource
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
            'icon' => $this->icon,
            'milestone_type' => $this->milestone_type->value,
            'milestone_value' => $this->milestone_value,
        ];
    }
}
