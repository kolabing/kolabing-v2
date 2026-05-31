<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PointEventType;
use App\Exceptions\CollaborationException;
use App\Models\Collaboration;
use App\Models\CollaborationFeedback;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

class CollaborationFeedbackService
{
    public function __construct(
        private readonly GamificationWalletService $walletService,
    ) {}

    /**
     * Submit the caller's rich feedback row. Awards XP on first submission.
     * If a mirrored stub already exists for this caller, this upgrades it in
     * place (without re-awarding XP) — the rich-fields just fill in.
     *
     * @param  array<string, mixed>  $payload  Validated feedback fields.
     *
     * @throws CollaborationException
     */
    public function submit(Collaboration $collaboration, Profile $reviewer, array $payload): CollaborationFeedback
    {
        $role = $this->resolveReviewerRole($collaboration, $reviewer);

        if ($role === null) {
            throw CollaborationException::notAParticipant();
        }

        $existing = $this->ownRow($collaboration, $reviewer);

        if ($existing !== null && ! $existing->mirrored_from_review) {
            throw CollaborationException::feedbackAlreadySubmitted();
        }

        return DB::transaction(function () use ($collaboration, $reviewer, $payload, $role, $existing): CollaborationFeedback {
            $attributes = $this->attributesForRole($reviewer, $payload);
            $attributes['reviewer_role'] = $role;
            $attributes['reviewer_type'] = $reviewer->user_type->value;
            $attributes['mirrored_from_review'] = false;

            if ($existing !== null) {
                $existing->fill($attributes);
                $existing->save();

                return $existing->fresh();
            }

            $feedback = CollaborationFeedback::create(array_merge($attributes, [
                'collaboration_id' => $collaboration->id,
                'reviewer_profile_id' => $reviewer->id,
            ]));

            // Per Q7: XP fires per party on /feedback POST, not on /complete.
            $this->walletService->awardPoints(
                $reviewer->id,
                PointEventType::CollaborationComplete->defaultPoints(),
                PointEventType::CollaborationComplete,
                $collaboration->id,
                'Submitted collaboration feedback',
            );

            return $feedback;
        });
    }

    /**
     * Update the caller's existing feedback row. Only permitted while the
     * partner has NOT yet submitted their own row.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws CollaborationException
     */
    public function edit(Collaboration $collaboration, Profile $reviewer, array $payload): CollaborationFeedback
    {
        $role = $this->resolveReviewerRole($collaboration, $reviewer);

        if ($role === null) {
            throw CollaborationException::notAParticipant();
        }

        $existing = $this->ownRow($collaboration, $reviewer);

        if ($existing === null) {
            throw CollaborationException::feedbackNotYetSubmitted();
        }

        if ($this->partnerRow($collaboration, $reviewer) !== null) {
            throw CollaborationException::feedbackLocked();
        }

        $attributes = $this->attributesForRole($reviewer, $payload);
        $attributes['mirrored_from_review'] = false;

        $existing->fill($attributes);
        $existing->save();

        return $existing->fresh();
    }

    /**
     * Create a minimal stub feedback row from a legacy /review payload, so the
     * /complete gate can succeed for clients that haven't migrated to the rich
     * /feedback endpoint. No-op if the caller already has any feedback row.
     *
     * No XP is awarded here — the review controller already awards
     * ReviewPosted XP for the review itself; the stub is purely for the gate.
     *
     * @param  array{rating: int, body?: string|null, would_collaborate_again?: bool|null}  $reviewPayload
     */
    public function mirrorFromReview(Collaboration $collaboration, Profile $reviewer, array $reviewPayload): ?CollaborationFeedback
    {
        $role = $this->resolveReviewerRole($collaboration, $reviewer);

        if ($role === null) {
            return null;
        }

        if ($this->ownRow($collaboration, $reviewer) !== null) {
            return null;
        }

        $attributes = [
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $reviewer->id,
            'reviewer_type' => $reviewer->user_type->value,
            'reviewer_role' => $role,
            'rating' => $reviewPayload['rating'],
            'would_recommend' => $reviewPayload['would_collaborate_again'] ?? null,
            'expectation_match' => null,
            'mirrored_from_review' => true,
        ];

        if ($reviewer->isCommunity() && isset($reviewPayload['body'])) {
            $attributes['benefits'] = $reviewPayload['body'];
        }

        return CollaborationFeedback::create($attributes);
    }

    /**
     * Reviewer types ('business' | 'community') of participants who have NOT
     * yet submitted feedback. Mirrored stubs count as submitted (the row
     * exists, so the gate doesn't block).
     *
     * Driven by the creator + applicant profiles' user_type — robust to the
     * `business_profile_id` / `community_profile_id` columns being null (they
     * can be when an extended profile row isn't filled in yet).
     *
     * @return array<int, string> Subset of ['business', 'community'].
     */
    public function pendingFeedbackFrom(Collaboration $collaboration): array
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile']);

        $participantTypes = collect([
            $collaboration->creatorProfile?->user_type?->value,
            $collaboration->applicantProfile?->user_type?->value,
        ])->filter()->unique()->values()->all();

        $submittedTypes = CollaborationFeedback::query()
            ->where('collaboration_id', $collaboration->id)
            ->pluck('reviewer_type')
            ->all();

        return array_values(array_diff($participantTypes, $submittedTypes));
    }

    /**
     * Number of feedback rows (including mirrored stubs) attached to this
     * collaboration. Used by the auto-timeout scheduler.
     */
    public function feedbackCount(Collaboration $collaboration): int
    {
        return CollaborationFeedback::query()
            ->where('collaboration_id', $collaboration->id)
            ->count();
    }

    public function ownRow(Collaboration $collaboration, Profile $reviewer): ?CollaborationFeedback
    {
        return CollaborationFeedback::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('reviewer_profile_id', $reviewer->id)
            ->first();
    }

    public function partnerRow(Collaboration $collaboration, Profile $reviewer): ?CollaborationFeedback
    {
        return CollaborationFeedback::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('reviewer_profile_id', '!=', $reviewer->id)
            ->first();
    }

    private function resolveReviewerRole(Collaboration $collaboration, Profile $reviewer): ?string
    {
        return match (true) {
            $reviewer->id === $collaboration->creator_profile_id => 'creator',
            $reviewer->id === $collaboration->applicant_profile_id => 'applicant',
            default => null,
        };
    }

    /**
     * Drop fields the reviewer's role isn't allowed to set. The FormRequest
     * already 422s on these, but defence-in-depth on the service side too.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function attributesForRole(Profile $reviewer, array $payload): array
    {
        $shared = [
            'rating' => $payload['rating'] ?? null,
            'expectation_match' => $payload['expectation_match'] ?? null,
            'would_recommend' => $payload['would_recommend'] ?? null,
            'posts_reels' => $payload['posts_reels'] ?? null,
        ];

        if ($reviewer->isBusiness()) {
            return array_merge($shared, [
                'stories_posted' => $payload['stories_posted'] ?? null,
                'revenue' => $payload['revenue'] ?? null,
                'benefits' => null,
            ]);
        }

        return array_merge($shared, [
            'stories_posted' => null,
            'revenue' => null,
            'benefits' => $payload['benefits'] ?? null,
        ]);
    }
}
