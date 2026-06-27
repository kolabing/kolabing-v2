<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\CollaborationStatus;
use App\Models\Collaboration;
use App\Models\CollaborationCompletion;
use App\Models\CollaborationFeedback;
use App\Models\CollaborationReview;
use App\Services\CollaborationCompletionService;
use App\Services\CollaborationFeedbackService;
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
            'kolab_id' => $this->kolab_id,
            'collab_opportunity_id' => $this->kolab_id,
            'kolab' => $this->when(
                $this->includeOpportunity,
                fn () => $this->whenLoaded('kolab', function () {
                    return new KolabResource($this->kolab);
                })
            ),
            'collab_opportunity' => $this->when(
                $this->includeOpportunity,
                fn () => $this->whenLoaded('kolab', function () {
                    return new OpportunitySummaryResource($this->kolab);
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
            'challenge_bonuses' => $this->whenLoaded('challengeBonuses', function () {
                return $this->challengeBonuses->map(fn ($bonus): array => [
                    'challenge_id' => $bonus->challenge_id,
                    'bonus_type' => $bonus->bonus_type->value,
                    'bonus_value' => $bonus->bonus_value,
                    'bonus_description' => $bonus->bonus_description,
                    'set_by_profile_id' => $bonus->set_by_profile_id,
                ])->values();
            }),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'completion_reason' => $this->completion_reason,
            'completed_by_profile_id' => $this->completed_by_profile_id,
            'auto_completed_at' => $this->auto_completed_at?->toIso8601String(),
            'pending_feedback_from' => $this->pendingFeedbackFrom(),
            'viewer_must_submit_feedback' => $this->viewerMustSubmitFeedback($currentProfile),
            'own_feedback' => $this->ownFeedback($currentProfile),
            'partner_feedback' => $this->partnerFeedback($currentProfile),
            // Lightweight required confirmation step (PR 1, 2026-06-26) — this,
            // not feedback above, gates /complete. Feedback is now optional.
            'pending_completion_from' => $this->pendingCompletionFrom(),
            'viewer_must_confirm_completion' => $this->viewerMustConfirmCompletion($currentProfile),
            'own_completion' => $this->ownCompletion($currentProfile),
            'partner_completion_status' => $this->partnerCompletionStatus($currentProfile),
            'reviews' => $this->whenLoaded('reviews', function () {
                return $this->reviews->map(fn ($review): array => [
                    'reviewer_role' => $review->reviewer_role,
                    'rating' => $review->rating,
                    'note' => $review->body ?? $review->note,
                    'body' => $review->body ?? $review->note,
                    'would_collaborate_again' => $review->would_collaborate_again,
                    'created_at' => $review->created_at?->toIso8601String(),
                ])->values();
            }),
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
            'has_reviewed' => $this->when(
                $currentProfile !== null && $this->isCompleted(),
                fn () => CollaborationReview::query()
                    ->where('collaboration_id', $this->id)
                    ->where('reviewer_profile_id', $currentProfile->id)
                    ->exists()
            ),
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

    /**
     * Cache and return the feedback rows for this collaboration.
     *
     * @return \Illuminate\Support\Collection<int, CollaborationFeedback>
     */
    private function feedbackRows(): \Illuminate\Support\Collection
    {
        if ($this->resource->relationLoaded('feedbacks')) {
            return $this->resource->feedbacks;
        }

        /** @var \Illuminate\Support\Collection<int, CollaborationFeedback>|null $cached */
        static $cache = [];

        if (! isset($cache[$this->id])) {
            $cache[$this->id] = CollaborationFeedback::query()
                ->where('collaboration_id', $this->id)
                ->get();
        }

        return $cache[$this->id];
    }

    /**
     * Reviewer types ('business' | 'community') of participants who have NOT
     * yet submitted feedback. Mirrored stubs count as submitted.
     *
     * @return array<int, string>
     */
    private function pendingFeedbackFrom(): array
    {
        /** @var Collaboration $collab */
        $collab = $this->resource;

        return app(CollaborationFeedbackService::class)->pendingFeedbackFrom($collab);
    }

    /**
     * @param  \App\Models\Profile|null  $profile
     */
    private function viewerMustSubmitFeedback($profile): bool
    {
        if ($profile === null) {
            return false;
        }

        return in_array($profile->user_type->value, $this->pendingFeedbackFrom(), true);
    }

    /**
     * The caller's own feedback row, in full (including private fields).
     *
     * @param  \App\Models\Profile|null  $profile
     * @return array<string, mixed>|null
     */
    private function ownFeedback($profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        $row = $this->feedbackRows()->firstWhere('reviewer_profile_id', $profile->id);
        if ($row === null) {
            return null;
        }

        return [
            'id' => $row->id,
            'reviewer_role' => $row->reviewer_role,
            'reviewer_type' => $row->reviewer_type,
            'rating' => $row->rating,
            'expectation_match' => $row->expectation_match,
            'would_recommend' => $row->would_recommend,
            'would_collaborate_again' => $row->would_collaborate_again,
            'posts_reels' => $row->posts_reels,
            'stories_posted' => $row->stories_posted,
            'revenue' => $row->revenue,
            'benefits' => $row->benefits,
            'mirrored_from_review' => $row->mirrored_from_review,
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The partner's feedback — public subset only — and only when BOTH parties
     * have a non-mirrored row (Q10). Mirrored stubs do not unlock cross-visibility.
     *
     * @param  \App\Models\Profile|null  $profile
     * @return array<string, mixed>|null
     */
    private function partnerFeedback($profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        $rows = $this->feedbackRows();
        $real = $rows->where('mirrored_from_review', false);
        if ($real->count() < 2) {
            return null;
        }

        $partner = $real->first(fn (CollaborationFeedback $row): bool => $row->reviewer_profile_id !== $profile->id);
        if ($partner === null) {
            return null;
        }

        return $partner->publicSubset();
    }

    /**
     * Cache and return the completion-confirmation rows for this collaboration.
     *
     * @return \Illuminate\Support\Collection<int, CollaborationCompletion>
     */
    private function completionRows(): \Illuminate\Support\Collection
    {
        if ($this->resource->relationLoaded('completions')) {
            return $this->resource->completions;
        }

        /** @var array<string, \Illuminate\Support\Collection<int, CollaborationCompletion>> $cache */
        static $cache = [];

        if (! isset($cache[$this->id])) {
            $cache[$this->id] = CollaborationCompletion::query()
                ->where('collaboration_id', $this->id)
                ->get();
        }

        return $cache[$this->id];
    }

    /**
     * Reviewer types ('business' | 'community') who have NOT submitted ANY
     * completion confirmation yet.
     *
     * @return array<int, string>
     */
    private function pendingCompletionFrom(): array
    {
        /** @var Collaboration $collab */
        $collab = $this->resource;

        return app(CollaborationCompletionService::class)->pendingConfirmationFrom($collab);
    }

    /**
     * @param  \App\Models\Profile|null  $profile
     */
    private function viewerMustConfirmCompletion($profile): bool
    {
        if ($profile === null) {
            return false;
        }

        return in_array($profile->user_type->value, $this->pendingCompletionFrom(), true);
    }

    /**
     * @param  \App\Models\Profile|null  $profile
     * @return array<string, mixed>|null
     */
    private function ownCompletion($profile): ?array
    {
        if ($profile === null) {
            return null;
        }

        $row = $this->completionRows()->firstWhere('profile_id', $profile->id);
        if ($row === null) {
            return null;
        }

        return [
            'status' => $row->status->value,
            'note' => $row->note,
            'created_at' => $row->created_at?->toIso8601String(),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    /**
     * The partner's confirmation status only (no note — keeps this minimal,
     * matching the lightweight nature of this step). Null until the partner
     * has submitted.
     *
     * @param  \App\Models\Profile|null  $profile
     */
    private function partnerCompletionStatus($profile): ?string
    {
        if ($profile === null) {
            return null;
        }

        $partner = $this->completionRows()->first(
            fn (CollaborationCompletion $row): bool => $row->profile_id !== $profile->id
        );

        return $partner?->status->value;
    }
}
