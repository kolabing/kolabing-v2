<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\UserType;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flat public profile resource - no sensitive data (email, phone).
 *
 * @mixin Profile
 */
class PublicProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extendedProfile = $this->getExtendedProfile();
        $businessCategories = $this->isBusiness()
            ? $this->businessProfile?->normalizedCategories() ?? []
            : [];

        return [
            'id' => $this->id,
            'user_type' => $this->user_type->value,
            'display_name' => $extendedProfile?->name,
            'avatar_url' => $this->avatar_url,
            'about' => $extendedProfile?->about,
            'type' => $this->user_type === UserType::Business
                ? $this->businessProfile?->primaryCategory()
                : $extendedProfile?->community_type,
            'business_type' => $this->when($this->user_type === UserType::Business, fn () => $this->businessProfile?->primaryCategory()),
            'categories' => $this->when($this->user_type === UserType::Business, fn () => $businessCategories),
            'city_name' => $extendedProfile?->city?->name,
            'instagram' => $extendedProfile?->instagram,
            'tiktok' => $this->user_type === UserType::Community
                ? $extendedProfile?->tiktok
                : null,
            'website' => $extendedProfile?->website,
            'profile_photo' => $extendedProfile?->profile_photo,
        ];
    }
}
