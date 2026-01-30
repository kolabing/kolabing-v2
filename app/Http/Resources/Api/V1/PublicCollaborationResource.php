<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lightweight collaboration resource for public profile pages.
 *
 * @mixin Collaboration
 */
class PublicCollaborationResource extends JsonResource
{
    /**
     * The profile whose public page is being viewed.
     */
    private ?Profile $viewedProfile = null;

    /**
     * Set the profile context for determining partner info.
     */
    public function forProfile(Profile $profile): self
    {
        $this->viewedProfile = $profile;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $partner = $this->getPartner();

        return [
            'id' => $this->id,
            'title' => $this->collabOpportunity?->title,
            'partner_name' => $partner?->getExtendedProfile()?->name,
            'partner_avatar_url' => $partner?->avatar_url,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'status' => $this->status->value,
        ];
    }

    /**
     * Get the partner profile (the other party in the collaboration).
     */
    private function getPartner(): ?Profile
    {
        if (! $this->viewedProfile) {
            return null;
        }

        if ($this->creator_profile_id === $this->viewedProfile->id) {
            return $this->applicantProfile;
        }

        return $this->creatorProfile;
    }
}
