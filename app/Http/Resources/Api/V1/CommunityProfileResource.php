<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

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
        $logo = $this->absoluteUrl($this->profile_photo ?? $this->profile?->avatar_url);

        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'name' => $this->name,
            'about' => $this->about,
            'community_type' => $this->community_type,
            'type_label' => $this->formatTypeLabel($this->community_type),
            'city' => $this->whenLoaded('city', function () {
                return $this->city ? new CityResource($this->city) : null;
            }),
            'instagram' => $this->instagram,
            'tiktok' => $this->tiktok,
            'website' => $this->website,
            'profile_photo' => $logo,
            'logo_url' => $logo,
            'is_featured' => $this->is_featured,
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
     * Format a raw community type slug into a human label.
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
