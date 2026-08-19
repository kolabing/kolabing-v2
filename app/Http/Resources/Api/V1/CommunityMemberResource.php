<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CommunityMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin CommunityMember
 */
class CommunityMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'community_id' => $this->community_id,
            'profile_id' => $this->profile_id,
            'tier' => $this->whenLoaded('tier', fn () => $this->tier
                ? new CommunityTierResource($this->tier)
                : null),
            'tier_id' => $this->tier_id,
            'can_manage' => $this->can_manage,
            'status' => $this->status->value,
            'joined_at' => $this->joined_at?->toIso8601String(),
            'tier_assigned_at' => $this->tier_assigned_at?->toIso8601String(),
            'profile' => $this->whenLoaded('profile', fn () => [
                'name' => $this->profileDisplayName(),
                'handle' => $this->profile?->handle,
                'email' => $this->profile?->email,
                'avatar_url' => $this->profileAvatarUrl(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * profiles.name is the canonical display name (set at onboarding for every
     * user type). attendee_profiles carries no name column at all, so the old
     * extended-profile-first order rendered every community member as their
     * email prefix. Extended names stay as a fallback for business/community
     * profiles created before profiles.name existed.
     */
    private function profileDisplayName(): ?string
    {
        if (filled($this->profile?->name)) {
            return $this->profile->name;
        }

        $extended = $this->profile?->getExtendedProfile();

        if ($extended && ! empty($extended->name)) {
            return $extended->name;
        }

        return $this->profile ? Str::before($this->profile->email, '@') : null;
    }

    private function profileAvatarUrl(): ?string
    {
        $extended = $this->profile?->getExtendedProfile();

        return $this->profile?->avatar_url
            ?? ($extended->profile_photo ?? null);
    }
}
