<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\GamificationBadgeSlug;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GamificationBadgeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var GamificationBadgeSlug $slug */
        $slug = $this->resource['slug'];
        $earnedBadge = $this->resource['earned_badge'] ?? null;

        return [
            'slug' => $slug->value,
            'name' => $slug->displayName(),
            'description' => $slug->description(),
            'is_unlocked' => $earnedBadge !== null,
            'earned_at' => $earnedBadge?->earned_at?->toIso8601String(),
        ];
    }
}
