<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

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

        $logo = $this->absoluteUrl($this->profile_photo ?? $this->profile?->avatar_url);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'about' => $this->about,
            'offering' => $this->offering,
            'business_type' => $this->primaryCategory(),
            'type_label' => $this->formatTypeLabel($this->primaryCategory()),
            'categories' => $this->normalizedCategories(),
            'has_venue' => (bool) $this->has_venue,
            'city' => $city,
            'target_city_ids' => $this->target_city_ids ?? [],
            'instagram' => $this->instagram,
            'website' => $this->website,
            'profile_photo' => $logo,
            'logo_url' => $logo,
            'primary_venue' => $this->primary_venue,
            'offer_photos' => $this->offer_photos ?? [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Convert a stored logo value into an absolute URL.
     */
    private function absoluteUrl(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        return rtrim((string) config('app.url'), '/').'/'.ltrim($value, '/');
    }

    /**
     * Format a raw business type slug into a human label.
     */
    private function formatTypeLabel(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $key = Str::of($value)->lower()->replace([' ', '-'], '_')->trim('_')->value();

        return match ($key) {
            'food_drink', 'food_and_drink' => 'Food & Drink',
            default => Str::of($key)->replace('_', ' ')->title()->value(),
        };
    }
}
