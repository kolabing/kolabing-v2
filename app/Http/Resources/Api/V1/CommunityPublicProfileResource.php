<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\UserType;
use App\Http\Resources\Api\V1\Concerns\EmitsVerificationFields;
use App\Models\Profile;
use App\Support\PublicProfileLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Profile
 */
class CommunityPublicProfileResource extends JsonResource
{
    use EmitsVerificationFields;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Role-aware: businesses and communities keep their details on different
        // extended-profile tables, so read through the one this profile actually has.
        $extended = $this->getExtendedProfile();
        $isBusiness = $this->user_type === UserType::Business;

        return [
            'id' => $this->id,
            'user_type' => $this->user_type->value,
            'display_name' => $extended?->name,
            'avatar_url' => $this->avatar_url ?: $extended?->profile_photo,
            'about' => $extended?->about,
            'type' => $isBusiness ? $this->businessProfile?->business_type : $this->communityProfile?->community_type,
            // Kept for the community endpoint's existing consumers; null for a business.
            'community_type' => $this->communityProfile?->community_type,
            'business_type' => $isBusiness ? $this->businessProfile?->business_type : null,
            'categories' => $isBusiness ? ($this->businessProfile?->normalizedCategories() ?? []) : [],
            'city_name' => $extended?->city?->name ?? ($isBusiness ? $this->businessProfile?->city_name : null),
            'instagram' => $extended?->instagram,
            // Only communities collect a TikTok handle.
            'tiktok' => $this->communityProfile?->tiktok,
            'website' => $extended?->website,
            'profile_photo' => $extended?->profile_photo,
            // Verification is a community-only programme; a business passes null and
            // serialises as unverified rather than throwing on the type hint.
            ...$this->verificationFields($this->communityProfile, $request, $this->id),
            'gallery' => $this->getAttribute('community_public_gallery') ?? [],
            'photos' => $this->getAttribute('community_public_photos') ?? [],
            'past_events' => $this->getAttribute('community_public_past_events') ?? [],
            'past_collaborations' => $this->getAttribute('community_public_past_collaborations') ?? [],
            // The shareable marketing URL, so clients never rebuild the slug rule.
            'public_url' => PublicProfileLink::urlFor($this->resource),
            'public_stats' => $this->getAttribute('community_public_stats') ?? [
                'completed_collaborations_count' => 0,
                'published_kolabs_count' => 0,
                'past_events_count' => 0,
            ],
        ];
    }
}
