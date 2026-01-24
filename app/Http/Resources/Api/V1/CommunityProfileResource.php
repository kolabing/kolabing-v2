<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CommunityProfile
 */
class CommunityProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'about' => $this->about,
            'community_type' => $this->community_type,
            'city' => $this->whenLoaded('city', function () {
                return $this->city ? new CityResource($this->city) : null;
            }),
            'instagram' => $this->instagram,
            'tiktok' => $this->tiktok,
            'website' => $this->website,
            'profile_photo' => $this->profile_photo,
            'is_featured' => $this->is_featured,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
