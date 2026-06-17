<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Http\Resources\Api\V1\Concerns\EmitsVerificationFields;
use App\Models\Profile;
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
        return [
            'id' => $this->id,
            'user_type' => $this->user_type->value,
            'display_name' => $this->communityProfile?->name,
            'avatar_url' => $this->avatar_url ?: $this->communityProfile?->profile_photo,
            'about' => $this->communityProfile?->about,
            'type' => $this->communityProfile?->community_type,
            'community_type' => $this->communityProfile?->community_type,
            'city_name' => $this->communityProfile?->city?->name,
            'instagram' => $this->communityProfile?->instagram,
            'tiktok' => $this->communityProfile?->tiktok,
            'website' => $this->communityProfile?->website,
            'profile_photo' => $this->communityProfile?->profile_photo,
            ...$this->verificationFields($this->communityProfile, $request, $this->id),
            'gallery' => $this->getAttribute('community_public_gallery') ?? [],
            'photos' => $this->getAttribute('community_public_photos') ?? [],
            'past_events' => $this->getAttribute('community_public_past_events') ?? [],
            'past_collaborations' => $this->getAttribute('community_public_past_collaborations') ?? [],
            'public_stats' => $this->getAttribute('community_public_stats') ?? [
                'completed_collaborations_count' => 0,
                'published_kolabs_count' => 0,
                'past_events_count' => 0,
            ],
        ];
    }
}
