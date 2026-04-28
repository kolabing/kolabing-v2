<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BusinessProfile
 */
class BusinessProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $city = null;

        if ($this->relationLoaded('city') && $this->city) {
            $city = new CityResource($this->city);
        } elseif ($this->city_name !== null) {
            $city = [
                'id' => null,
                'name' => $this->city_name,
                'country' => $this->city_country,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'about' => $this->about,
            'business_type' => $this->primaryCategory(),
            'categories' => $this->normalizedCategories(),
            'city' => $city,
            'instagram' => $this->instagram,
            'website' => $this->website,
            'profile_photo' => $this->profile_photo,
            'primary_venue' => $this->primary_venue,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
