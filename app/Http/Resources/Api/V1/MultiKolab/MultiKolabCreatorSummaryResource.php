<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\MultiKolab;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Minimal creator identity for Multi-Kolab Explore/detail — exactly the
 * `{id, display_name, avatar_url}` shape frozen in the API contract §6.
 * Deliberately smaller than {@see \App\Http\Resources\Api\V1\ProfileSummaryResource}
 * (which pulls in portfolio photos, verification, categories, ...) since
 * none of that is part of this contract and pulling it in would risk N+1s
 * this resource doesn't need.
 *
 * @mixin Profile
 */
class MultiKolabCreatorSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extendedProfile = $this->isBusiness() ? $this->businessProfile : $this->communityProfile;

        return [
            'id' => $this->id,
            'display_name' => $extendedProfile?->name,
            'avatar_url' => $this->avatar_url,
        ];
    }
}
