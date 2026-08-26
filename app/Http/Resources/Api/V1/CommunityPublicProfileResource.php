<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\UserType;
use App\Http\Resources\Api\V1\Concerns\EmitsVerificationFields;
use App\Models\Profile;
use App\Support\CommunityIdentityMask;
use App\Support\PublicProfileLink;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The profile page both clients open: `GET /profiles/{id}/public-profile` and
 * `GET /communities/{id}/public-profile`.
 *
 * **The identity mask is applied** — see {@see CommunityIdentityMask}. ROLES §2.5
 * does not let a free business open a community's full profile or contact, and this
 * resource is that profile (BE-FX-22). Note what it carries beyond the name: the
 * gallery, past events with partner names and dates, past collaborations, and
 * `public_url`, which resolves the identity on its own through the handle.
 *
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
        $masked = CommunityIdentityMask::applies($request->user(), $this->resource);

        return [
            'id' => $this->id,
            'user_type' => $this->user_type->value,
            'identity_masked' => $masked,
            'display_name' => $masked ? null : $extended?->name,
            'avatar_url' => $masked ? null : ($this->avatar_url ?: $extended?->profile_photo),
            'about' => $masked ? null : $extended?->about,
            'type' => $isBusiness ? $this->businessProfile?->business_type : $this->communityProfile?->community_type,
            // Kept for the community endpoint's existing consumers; null for a business.
            'community_type' => $this->communityProfile?->community_type,
            'business_type' => $isBusiness ? $this->businessProfile?->business_type : null,
            'categories' => $isBusiness ? ($this->businessProfile?->normalizedCategories() ?? []) : [],
            'city_name' => $extended?->city?->name ?? ($isBusiness ? $this->businessProfile?->city_name : null),
            'instagram' => $masked ? null : $extended?->instagram,
            // Only communities collect a TikTok handle.
            'tiktok' => $masked ? null : $this->communityProfile?->tiktok,
            'website' => $masked ? null : $extended?->website,
            'profile_photo' => $masked ? null : $extended?->profile_photo,
            // Verification is a community-only programme; a business passes null and
            // serialises as unverified rather than throwing on the type hint.
            ...$this->verificationFields($this->communityProfile, $request, $this->id),
            // Past events and collaborations name the partners; the gallery is the
            // community's own photographs. §4.2 withholds exactly this from a viewer
            // who has not earned it, and it is what "the full profile" means.
            'gallery' => $masked ? [] : ($this->getAttribute('community_public_gallery') ?? []),
            'photos' => $masked ? [] : ($this->getAttribute('community_public_photos') ?? []),
            'past_events' => $masked ? [] : ($this->getAttribute('community_public_past_events') ?? []),
            'past_collaborations' => $masked ? [] : ($this->getAttribute('community_public_past_collaborations') ?? []),
            // The shareable marketing URL, so clients never rebuild the slug rule.
            // Withheld under the mask: it is built from the handle, so it hands over
            // the name in the one field a mask would be assumed to have covered.
            'public_url' => $masked ? null : PublicProfileLink::urlFor($this->resource),
            'public_stats' => $this->getAttribute('community_public_stats') ?? [
                'completed_collaborations_count' => 0,
                'published_kolabs_count' => 0,
                'past_events_count' => 0,
            ],
        ];
    }
}
