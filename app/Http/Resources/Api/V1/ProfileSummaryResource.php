<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight profile resource for nested data in other resources.
 *
 * @mixin Profile
 */
class ProfileSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extendedProfile = $this->getExtendedProfile();
        $city = $extendedProfile?->city ?? null;

        return [
            'id' => $this->id,
            'user_type' => $this->user_type->value,
            'display_name' => $extendedProfile?->name,
            'avatar_url' => $this->avatar_url,
            'city' => $city ? new CityResource($city) : null,
            'business_type' => $this->when($this->isBusiness(), fn () => $this->businessProfile?->business_type),
            'community_type' => $this->when($this->isCommunity(), fn () => $this->communityProfile?->community_type),
        ];
    }
}
