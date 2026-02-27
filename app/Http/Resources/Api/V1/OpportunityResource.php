<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\CollabOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CollabOpportunity
 */
class OpportunityResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $currentProfile = $request->user();
        $isOwn = $currentProfile && $this->creator_profile_id === $currentProfile->id;
        $myApplication = $this->getMyApplication($currentProfile);

        $canPublish = $isOwn
            && $this->isDraft()
            && (! $currentProfile->isBusiness() || $currentProfile->hasActiveSubscription());

        return [
            'id' => $this->id,
            'creator_profile' => $this->whenLoaded('creatorProfile', function () {
                return new ProfileSummaryResource($this->creatorProfile);
            }),
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'business_offer' => $this->business_offer,
            'community_deliverables' => $this->community_deliverables,
            'categories' => $this->categories,
            'availability_mode' => $this->availability_mode,
            'availability_start' => $this->availability_start?->format('Y-m-d'),
            'availability_end' => $this->availability_end?->format('Y-m-d'),
            'venue_mode' => $this->venue_mode,
            'address' => $this->address,
            'preferred_city' => $this->preferred_city,
            'offer_photo' => $this->offer_photo,
            'published_at' => $this->published_at?->toIso8601String(),
            'applications_count' => $this->whenCounted('applications'),
            'is_own' => $isOwn,
            'can_publish' => $this->when($isOwn, $canPublish),
            'has_applied' => $this->when($currentProfile !== null && ! $isOwn, fn () => $myApplication !== null),
            'my_application' => $this->when(
                $currentProfile !== null && ! $isOwn && $myApplication !== null,
                fn () => new ApplicationResource($myApplication)
            ),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get the current user's application for this opportunity.
     *
     * @param  \App\Models\Profile|null  $profile
     * @return \App\Models\Application|null
     */
    private function getMyApplication($profile)
    {
        if (! $profile) {
            return null;
        }

        // Check if applications are loaded to avoid N+1
        if ($this->relationLoaded('applications')) {
            return $this->applications->first(fn ($app) => $app->applicant_profile_id === $profile->id);
        }

        return null;
    }
}
