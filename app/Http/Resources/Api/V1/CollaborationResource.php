<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\CollaborationStatus;
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
            'event_id' => $this->event_id,
            'qr_code_url' => $this->qr_code_url,
            'challenges' => $this->whenLoaded('challenges', function () {
                return ChallengeResource::collection($this->challenges);
            }),
            'selected_challenge_ids' => $this->whenLoaded('challenges', function () {
                return $this->challenges->pluck('id')->values();
            }),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'reviews' => $this->whenLoaded('reviews', function () {
                return $this->reviews->map(fn ($review): array => [
                    'reviewer_role' => $review->reviewer_role,
                    'rating' => $review->rating,
                    'note' => $review->note,
                    'created_at' => $review->created_at?->toIso8601String(),
                ])->values();
            }),
            'feedback' => $this->when(
                $this->relationLoaded('feedback'),
                fn () => $this->feedback->map(fn ($feedback): array => [
                    'reviewer_profile_id' => $feedback->reviewer_profile_id,
                    'reviewer_type' => $feedback->reviewer_type,
                    'reviewer_role' => $feedback->reviewer_role,
                    'rating' => $feedback->rating,
                    'posts_reels' => $feedback->posts_reels,
                    'expectation_match' => $feedback->expectation_match,
                    'would_recommend' => $feedback->would_recommend,
                    'stories_posted' => $feedback->stories_posted,
                    'revenue' => $feedback->revenue,
                    'benefits' => $feedback->benefits,
                    'created_at' => $feedback->created_at?->toIso8601String(),
                ])->values()
            ),
            // True only when the VIEWING profile is a business whose subscription
            // has lapsed while this collaboration is still ongoing, so the client
            // blurs the business side until it resubscribes (decision §2.8).
            // Communities NEVER receive this flag (always false).
            'viewer_must_resubscribe' => $this->viewerMustResubscribe($currentProfile),
            'my_role' => $this->when($currentProfile !== null, fn () => $myRole),
            'actions' => $this->when($currentProfile !== null, [
                'can_activate' => $this->canBeActivated(),
                'can_complete' => $this->canBeCompleted(),
                'can_cancel' => $this->canBeCancelled(),
            ]),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Subscription-lapse re-gate flag (ROLES-AND-PERMISSIONS.md §2.8).
     *
     * Returns true ONLY when the viewing profile is a business
     * (isBusiness()) without an active subscription (!hasActiveSubscription())
     * AND the collaboration is still ongoing (scheduled or active). In that
     * case the client blurs the business side / withdraws ongoing access until
     * the business resubscribes.
     *
     * The community counterparty is never affected: hasActiveSubscription()
     * already returns false for any non-business, but isBusiness() short-circuits
     * first so a community viewer always gets false. A completed or cancelled
     * collaboration is not "ongoing", so it is never re-gated.
     *
     * @param  \App\Models\Profile|null  $profile
     */
    private function viewerMustResubscribe($profile): bool
    {
        if ($profile === null || ! $profile->isBusiness()) {
            return false;
        }

        if ($profile->hasActiveSubscription()) {
            return false;
        }

        return $this->status === CollaborationStatus::Scheduled
            || $this->status === CollaborationStatus::Active;
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
