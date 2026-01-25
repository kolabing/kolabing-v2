<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Collaboration;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Collaboration
 */
class CollaborationResource extends JsonResource
{
    /**
     * Indicates if the resource should include application details.
     */
    protected bool $includeApplication = true;

    /**
     * Indicates if the resource should include opportunity details.
     */
    protected bool $includeOpportunity = true;

    /**
     * Disable application inclusion to prevent circular references.
     */
    public function withoutApplication(): self
    {
        $this->includeApplication = false;

        return $this;
    }

    /**
     * Disable opportunity inclusion to prevent circular references.
     */
    public function withoutOpportunity(): self
    {
        $this->includeOpportunity = false;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentProfile = $request->user();
        $myRole = $this->getMyRole($currentProfile);

        return [
            'id' => $this->id,
            'application' => $this->when(
                $this->includeApplication,
                fn () => $this->whenLoaded('application', function () {
                    return (new ApplicationResource($this->application))->withoutOpportunity();
                })
            ),
            'collab_opportunity' => $this->when(
                $this->includeOpportunity,
                fn () => $this->whenLoaded('collabOpportunity', function () {
                    return new OpportunitySummaryResource($this->collabOpportunity);
                })
            ),
            'creator_profile' => $this->whenLoaded('creatorProfile', function () {
                return new ProfileSummaryResource($this->creatorProfile);
            }),
            'applicant_profile' => $this->whenLoaded('applicantProfile', function () {
                return new ProfileSummaryResource($this->applicantProfile);
            }),
            'business_profile' => $this->whenLoaded('businessProfile', function () {
                return $this->businessProfile
                    ? new BusinessProfileResource($this->businessProfile)
                    : null;
            }),
            'community_profile' => $this->whenLoaded('communityProfile', function () {
                return $this->communityProfile
                    ? new CommunityProfileResource($this->communityProfile)
                    : null;
            }),
            'status' => $this->status->value,
            'scheduled_date' => $this->scheduled_date?->format('Y-m-d'),
            'contact_methods' => $this->contact_methods,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'my_role' => $this->when($currentProfile !== null, fn () => $myRole),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Determine the current user's role in this collaboration.
     *
     * @param  \App\Models\Profile|null  $profile
     */
    private function getMyRole($profile): ?string
    {
        if (! $profile) {
            return null;
        }

        if ($this->creator_profile_id === $profile->id) {
            return 'creator';
        }

        if ($this->applicant_profile_id === $profile->id) {
            return 'applicant';
        }

        return null;
    }
}
