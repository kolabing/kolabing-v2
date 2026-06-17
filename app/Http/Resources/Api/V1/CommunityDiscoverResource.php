<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\VerificationStatus;
use App\Models\Community;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Public discovery view of a community for attendees browsing communities to
 * join. Slim by design: no owner contact details, just enough to render a
 * discover card. `member_count` is provided as a precomputed attribute when the
 * collection was built with the count query; otherwise it falls back to the
 * model's memberCount() helper.
 *
 * @mixin Community
 */
class CommunityDiscoverResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'avatar_url' => $this->avatar_url,
            'type' => $this->type,
            'member_count' => $this->resolveMemberCount(),
            'join_policy' => $this->join_policy->value,
            'is_featured' => $this->is_featured,
            'matched' => (bool) ($this->getAttribute('interest_matched') ?? false),
            'leader_name' => $this->resolveLeaderName(),
            // Verified tick for the discover card. Channels are never exposed here.
            'verification_status' => $this->resolveVerificationStatus(),
            'is_verified' => $this->resolveVerificationStatus() === VerificationStatus::Verified->value,
        ];
    }

    /**
     * The owner community_profile's verification status (defaults to unverified).
     */
    private function resolveVerificationStatus(): string
    {
        return $this->communityProfile?->verification_status
            ?? VerificationStatus::Unverified->value;
    }

    /**
     * Prefer a precomputed active member count (set via withCount) when present,
     * falling back to the model helper so the resource is safe to reuse.
     */
    private function resolveMemberCount(): int
    {
        $precomputed = $this->getAttribute('active_members_count');

        if ($precomputed !== null) {
            return (int) $precomputed;
        }

        return $this->memberCount();
    }

    /**
     * The community leader's display name. Identity lives on the base profile
     * (profiles.name) with a fallback to the extended profile's name.
     */
    private function resolveLeaderName(): ?string
    {
        $owner = $this->owner;

        if ($owner === null) {
            return null;
        }

        return $owner->getExtendedProfile()?->name ?? $owner->name;
    }
}
