<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\PartnerReward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PartnerReward
 */
class PartnerRewardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'partner' => $this->partner,
            'description' => $this->description,
            'image_url' => $this->image_url,
            'cost_xp' => $this->cost_xp,
            'is_active' => $this->is_active,
        ];
    }
}
