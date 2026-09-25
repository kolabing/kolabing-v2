<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\EmitsVerificationFields;
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
    use EmitsVerificationFields;

    /**
     * Null = the caller did not ask for the identity mask (payload unchanged);
     * true/false = the {@see \App\Support\CommunityIdentityMask} decision,
     * emitted as `identity_masked`.
     */
    private ?bool $identityMasked = null;

    /**
     * Withhold the name, logo and photos (ROLES §2.5) when `$masked` is true.
     * `id` stays: `GET /profiles/{id}` applies the same mask, and the app
     * addresses applications by it.
     */
    public function maskIdentity(bool $masked): self
    {
        $this->identityMasked = $masked;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $extendedProfile = $this->getExtendedProfile();
        $city = $extendedProfile?->city ?? null;
        $masked = $this->identityMasked === true;

        return [
            'id' => $this->id,
            'user_type' => $this->user_type->value,
            'display_name' => $masked ? null : $extendedProfile?->name,
            'avatar_url' => $masked ? null : $this->avatar_url,
            'identity_masked' => $this->when($this->identityMasked !== null, $masked),
            'city' => $city ? new CityResource($city) : null,
            'business_type' => $this->when($this->isBusiness(), fn () => $this->businessProfile?->primaryCategory()),
            'categories' => $this->when($this->isBusiness(), fn () => $this->businessProfile?->normalizedCategories() ?? []),
            'community_type' => $this->when($this->isCommunity(), fn () => $this->communityProfile?->community_type),
            // Verified tick when this summary represents a community (so a business
            // viewing an application sees the community's verification state).
            ...($this->isCommunity()
                ? $this->summaryVerificationFields($request, $masked)
                : []),
            'portfolio_photos' => $masked ? [] : $this->getPortfolioPhotos(),
        ];
    }

    /**
     * Verification fields; under the mask `public_channels` (the community's
     * contact links) is emptied — ROLES §2.5 withholds contact.
     *
     * @return array<string, mixed>
     */
    private function summaryVerificationFields(Request $request, bool $masked): array
    {
        $fields = $this->verificationFields($this->communityProfile, $request, $this->id);

        if ($masked) {
            $fields['public_channels'] = [];
        }

        return $fields;
    }

    /**
     * Get merged portfolio photos from events and gallery (max 10).
     *
     * @return array<int, array{url: string, thumbnail_url: string|null, source: string}>
     */
    private function getPortfolioPhotos(): array
    {
        $photos = collect();

        if ($this->relationLoaded('events')) {
            foreach ($this->events as $event) {
                if ($event->relationLoaded('photos')) {
                    foreach ($event->photos as $photo) {
                        $photos->push([
                            'url' => $photo->url,
                            'thumbnail_url' => $photo->thumbnail_url,
                            'source' => 'event',
                        ]);
                    }
                }
            }
        }

        if ($this->relationLoaded('galleryPhotos')) {
            foreach ($this->galleryPhotos as $photo) {
                $photos->push([
                    'url' => $photo->url,
                    'thumbnail_url' => null,
                    'source' => 'gallery',
                ]);
            }
        }

        return $photos->take(10)->values()->all();
    }
}
