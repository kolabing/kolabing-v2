<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinRequestStatus;
use App\Http\Resources\Api\V1\Concerns\EmitsVerificationFields;
use App\Models\Community;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Community
 */
class CommunityResource extends JsonResource
{
    use EmitsVerificationFields;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Profile|null $viewer */
        $viewer = $request->user();

        return [
            'id' => $this->id,
            'owner_profile_id' => $this->owner_profile_id,
            'community_profile_id' => $this->community_profile_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'description' => $this->description,
            // Fall back to the owner community profile's logo when the community
            // row has no avatar (logos are set on community_profiles.profile_photo
            // at onboarding) — matches CommunityPublicProfileResource so the
            // community preview/cards render the picture. App normalizes the URL.
            'avatar_url' => $this->avatar_url ?: $this->communityProfile?->profile_photo,
            'is_primary' => $this->is_primary,
            'join_policy' => $this->join_policy->value,
            // Canonical shareable join link (always present). For invite_only
            // communities the bare slug link is not enough to bypass the gate —
            // owners/managers fetch the token link via GET /communities/{id}/invite.
            'invite_url' => $this->inviteUrl(),
            'member_count' => $this->memberCount(),
            // Viewer-scoped CTA hints (null-safe when unauthenticated).
            'is_member' => $viewer !== null ? $this->viewerIsMember($viewer) : false,
            'my_join_request_status' => $viewer !== null ? $this->viewerJoinRequestStatus($viewer) : null,
            // Viewer's per-community POINTS balance + tier (null when not a member).
            'my_points' => $viewer !== null ? $this->viewerPoints($viewer) : 0,
            'my_tier' => $viewer !== null ? $this->viewerTier($viewer) : null,
            // Verification tick (sourced from the owner's community_profile).
            ...$this->verificationFields($this->communityProfile, $request, $this->owner_profile_id),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function viewerPoints(Profile $viewer): int
    {
        return (int) (\App\Models\CommunityPoints::query()
            ->where('community_id', $this->id)
            ->where('profile_id', $viewer->id)
            ->value('points') ?? 0);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function viewerTier(Profile $viewer): ?array
    {
        $member = $this->members()
            ->where('profile_id', $viewer->id)
            ->with('tier')
            ->first();

        if ($member?->tier === null) {
            return null;
        }

        return [
            'id' => $member->tier->id,
            'name' => $member->tier->name,
            'color' => $member->tier->color,
            'rank' => $member->tier->rank,
        ];
    }

    private function viewerIsMember(Profile $viewer): bool
    {
        if ($viewer->id === $this->owner_profile_id) {
            return true;
        }

        return $this->members()
            ->where('profile_id', $viewer->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();
    }

    /**
     * The viewer's latest join-request status (null|pending|approved|declined).
     */
    private function viewerJoinRequestStatus(Profile $viewer): ?string
    {
        $status = $this->joinRequests()
            ->where('profile_id', $viewer->id)
            ->orderByDesc('created_at')
            ->value('status');

        if ($status === null) {
            return null;
        }

        return $status instanceof JoinRequestStatus ? $status->value : (string) $status;
    }
}
