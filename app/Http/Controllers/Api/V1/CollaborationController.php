<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\MissionTrigger;
use App\Enums\PointEventType;
use App\Exceptions\CollaborationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelCollaborationRequest;
use App\Http\Requests\Api\V1\CompleteCollaborationRequest;
use App\Http\Requests\Api\V1\StoreCollaborationCompletionRequest;
use App\Http\Requests\Api\V1\StoreCollaborationFeedbackRequest;
use App\Http\Requests\Api\V1\StoreCollaborationReviewRequest;
use App\Http\Requests\Api\V1\UpdateCollaborationFeedbackRequest;
use App\Http\Resources\Api\V1\CollaborationCollection;
use App\Http\Resources\Api\V1\CollaborationResource;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Profile;
use App\Services\BusinessPartnerStatusService;
use App\Services\CollaborationCompletionService;
use App\Services\CollaborationFeedbackService;
use App\Services\CollaborationService;
use App\Services\GamificationWalletService;
use App\Services\MissionService;
use App\Services\NotificationReminderService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaborationController extends Controller
{
    public function __construct(
        private readonly CollaborationService $collaborationService,
        private readonly GamificationWalletService $gamificationService,
        private readonly CollaborationFeedbackService $feedbackService,
        private readonly MissionService $missionService,
        private readonly CollaborationCompletionService $completionService,
        private readonly BusinessPartnerStatusService $businessPartnerStatusService,
        private readonly NotificationService $notificationService,
        private readonly NotificationReminderService $notificationReminderService,
    ) {}

    /**
     * List collaborations for the authenticated user.
     *
     * GET /api/v1/collaborations
     *
     * Query params:
     * - status: scheduled|active|completed|cancelled
     * - role: creator|applicant
     * - page: int
     * - per_page: int (default: 20, max: 100)
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->authorize('viewAny', Collaboration::class);

        $filters = [
            'status' => $request->query('status'),
            'role' => $request->query('role'),
        ];

        $perPage = min((int) $request->query('per_page', 20), 100);

        $collaborations = $this->collaborationService->getForProfile(
            $profile,
            $filters,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => new CollaborationCollection($collaborations),
            'meta' => [
                'current_page' => $collaborations->currentPage(),
                'last_page' => $collaborations->lastPage(),
                'per_page' => $collaborations->perPage(),
                'total' => $collaborations->total(),
            ],
        ]);
    }

    /**
     * Get collaboration details.
     *
     * GET /api/v1/collaborations/{collaboration}
     */
    public function show(Collaboration $collaboration): JsonResponse
    {
        $this->authorize('view', $collaboration);

        $collaboration = $this->collaborationService->findOrFail($collaboration->id);

        return response()->json([
            'success' => true,
            'data' => new CollaborationResource($collaboration),
        ]);
    }

    /**
     * Activate a scheduled collaboration.
     *
     * POST /api/v1/collaborations/{collaboration}/activate
     */
    public function activate(Request $request, Collaboration $collaboration): JsonResponse
    {
        $this->authorize('activate', $collaboration);

        /** @var Profile $actor */
        $actor = $request->user();

        try {
            $collaboration = $this->collaborationService->activate($collaboration, $actor);
        } catch (CollaborationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'invalid_status_transition',
                'errors' => $e->getContext(),
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => __('collaboration.activated'),
            'data' => new CollaborationResource($collaboration),
        ]);
    }

    /**
     * Mark collaboration as completed.
     *
     * POST /api/v1/collaborations/{collaboration}/complete
     */
    public function complete(
        CompleteCollaborationRequest $request,
        Collaboration $collaboration
    ): JsonResponse {
        $this->authorize('complete', $collaboration);
        $request->validated();

        /** @var Profile $caller */
        $caller = $request->user();

        try {
            $collaboration = $this->collaborationService->complete($collaboration, $caller);
        } catch (CollaborationException $e) {
            $context = $e->getContext();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $context['error_code'] ?? 'invalid_status_transition',
                'errors' => $context,
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => __('collaboration.completed'),
            'data' => new CollaborationResource($collaboration),
        ]);
    }

    /**
     * Submit (or update) the caller's lightweight completion confirmation
     * (yes/no/not_yet). This — not rich feedback — gates /complete as of the
     * 2026-06-26 completion-flow simplification (PR 1). XP fires once, on
     * first submission.
     *
     * POST /api/v1/collaborations/{collaboration}/completion
     */
    public function submitCompletion(
        StoreCollaborationCompletionRequest $request,
        Collaboration $collaboration,
    ): JsonResponse {
        /** @var Profile $confirmer */
        $confirmer = $request->user();

        $this->authorize('view', $collaboration);

        $validated = $request->validated();

        try {
            $completion = $this->completionService->submit(
                $collaboration,
                $confirmer,
                $validated['status'],
                $validated['note'] ?? null,
            );
        } catch (CollaborationException $e) {
            $context = $e->getContext();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $context['error_code'] ?? 'completion_confirmation_error',
                'errors' => $context,
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => __('Completion confirmation submitted.'),
            // Shape matches CollaborationResource::own_completion; internal
            // columns (id, profile_id, role, collaboration_id) are not exposed.
            'data' => [
                'status' => $completion->status->value,
                'note' => $completion->note,
                'created_at' => $completion->created_at?->toIso8601String(),
                'updated_at' => $completion->updated_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Submit the caller's rich completion feedback. Optional impact data —
     * no longer gates /complete (see submitCompletion above). Per the
     * 2026-06-01 feedback gate plan (§Q7): XP fires per party here.
     *
     * POST /api/v1/collaborations/{collaboration}/feedback
     */
    public function feedback(
        StoreCollaborationFeedbackRequest $request,
        Collaboration $collaboration,
    ): JsonResponse {
        /** @var Profile $reviewer */
        $reviewer = $request->user();

        $this->authorize('view', $collaboration);

        try {
            $feedback = $this->feedbackService->submit($collaboration, $reviewer, $request->validated());
        } catch (CollaborationException $e) {
            $context = $e->getContext();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $context['error_code'] ?? 'feedback_error',
                'errors' => $context,
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => __('Feedback submitted.'),
            'data' => $feedback,
        ], 201);
    }

    /**
     * Edit the caller's existing feedback row. Allowed only while the partner
     * has NOT yet submitted their own — once both rows exist, both lock.
     *
     * PUT /api/v1/collaborations/{collaboration}/feedback
     */
    public function updateFeedback(
        UpdateCollaborationFeedbackRequest $request,
        Collaboration $collaboration,
    ): JsonResponse {
        /** @var Profile $reviewer */
        $reviewer = $request->user();

        $this->authorize('view', $collaboration);

        try {
            $feedback = $this->feedbackService->edit($collaboration, $reviewer, $request->validated());
        } catch (CollaborationException $e) {
            $context = $e->getContext();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => $context['error_code'] ?? 'feedback_error',
                'errors' => $context,
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => __('Feedback updated.'),
            'data' => $feedback,
        ]);
    }

    /**
     * Cancel a collaboration.
     *
     * POST /api/v1/collaborations/{collaboration}/cancel
     */
    public function cancel(
        CancelCollaborationRequest $request,
        Collaboration $collaboration
    ): JsonResponse {
        $this->authorize('cancel', $collaboration);

        $validated = $request->validated();

        /** @var Profile $actor */
        $actor = $request->user();

        try {
            $collaboration = $this->collaborationService->cancel(
                $collaboration,
                $validated['reason'],
                $actor,
            );
        } catch (CollaborationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'invalid_status_transition',
                'errors' => $e->getContext(),
            ], $e->getStatusCode());
        }

        return response()->json([
            'success' => true,
            'message' => __('collaboration.cancelled'),
            'data' => new CollaborationResource($collaboration),
        ]);
    }

    /**
     * Submit a lightweight review for a completed collaboration.
     *
     * POST /api/v1/collaborations/{collaboration}/review
     *
     * Rules:
     * - Collaboration must not be cancelled (scheduled / active / completed all accepted)
     * - Reviewer must be a participant
     * - One review per reviewer per collaboration (idempotent: returns 200 on duplicate)
     * - reviewed_profile_id is derived from business/community profile columns,
     *   NOT from who submitted — ensuring correct cross-party attribution
     * - XP awarded only on first creation (not on duplicate)
     */
    public function review(
        StoreCollaborationReviewRequest $request,
        Collaboration $collaboration,
    ): JsonResponse {
        /** @var Profile $reviewer */
        $reviewer = $request->user();

        $this->authorize('view', $collaboration);

        if ($collaboration->isCancelled()) {
            return response()->json([
                'success' => false,
                'message' => 'Reviews cannot be left for cancelled collaborations.',
                'error_code' => 'collaboration_not_reviewable',
            ], 422);
        }

        $reviewerRole = match (true) {
            $reviewer->id === $collaboration->creator_profile_id => 'creator',
            $reviewer->id === $collaboration->applicant_profile_id => 'applicant',
            default => null,
        };

        if ($reviewerRole === null) {
            return response()->json([
                'success' => false,
                'message' => 'You are not a participant in this collaboration.',
                'error_code' => 'not_a_participant',
            ], 403);
        }

        $reviewedProfileId = $reviewerRole === 'creator'
            ? $collaboration->applicant_profile_id
            : $collaboration->creator_profile_id;

        // Idempotency: if a review already exists, return it without awarding XP again.
        $existing = CollaborationReview::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('reviewer_profile_id', $reviewer->id)
            ->first();

        if ($existing !== null) {
            return response()->json([
                'success' => true,
                'message' => 'Review already submitted.',
                'data' => $existing,
            ]);
        }

        $validated = $request->validated();

        $usesStarRatings = isset($validated['communication_rating']);

        // Legacy `rating` still gets stored so `overall_rating` has a fallback
        // and older clients reading it directly keep working. When the new
        // 5-star format is used, derive it as the rounded average.
        $legacyRating = $validated['rating']
            ?? ($usesStarRatings ? (int) round(
                ($validated['communication_rating'] + $validated['reliability_rating']
                    + $validated['fit_rating'] + $validated['value_rating'] + $validated['repeat_rating']) / 5
            ) : null);

        $review = CollaborationReview::create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewedProfileId,
            'reviewer_role' => $reviewerRole,
            'rating' => $legacyRating,
            'communication_rating' => $validated['communication_rating'] ?? null,
            'reliability_rating' => $validated['reliability_rating'] ?? null,
            'fit_rating' => $validated['fit_rating'] ?? null,
            'value_rating' => $validated['value_rating'] ?? null,
            'repeat_rating' => $validated['repeat_rating'] ?? null,
            'note' => isset($validated['body']) ? mb_substr((string) $validated['body'], 0, 200) : null,
            'body' => $validated['body'] ?? null,
            'public_comment' => $validated['public_comment'] ?? null,
            'would_collaborate_again' => $validated['would_collaborate_again'] ?? null,
        ]);

        // Award XP for leaving a review. The amount comes from xp_earn_rules
        // so the value can be edited from the admin without redeploying.
        $this->gamificationService->awardPoints(
            $reviewer->id,
            PointEventType::ReviewPosted,
            $collaboration->id,
            'Left a review for a completed Kolab',
        );

        // Mission progress: the reviewer's review_posted missions and the
        // reviewed party's review_received missions. Audience-scoped inside
        // record(), so each only matches missions for that profile's role.
        $this->missionService->recordSafely(
            $reviewer,
            MissionTrigger::ReviewPosted,
            1,
            ['reference_id' => $collaboration->id],
        );

        $reviewedProfile = Profile::find($reviewedProfileId);
        if ($reviewedProfile !== null) {
            $this->missionService->recordSafely(
                $reviewedProfile,
                MissionTrigger::ReviewReceived,
                1,
                ['reference_id' => $collaboration->id],
            );
            if ($reviewedProfile->isCommunity()) {
                $this->missionService->recordSafely(
                    $reviewedProfile,
                    MissionTrigger::BusinessReviewReceived,
                    1,
                    ['reference_id' => $collaboration->id],
                );
            }

            if ($reviewedProfile->isBusiness()) {
                $previousStatus = $this->businessPartnerStatusService->statusFor($reviewedProfile);
                $newStatus = $this->businessPartnerStatusService->recalculate($reviewedProfile);

                if ($newStatus !== $previousStatus) {
                    $this->notificationService->notifyPartnerStatusUpgraded($reviewedProfile, $newStatus);
                }
            }
        }

        $this->notificationReminderService->cancelReviewReminder($collaboration, $reviewer);

        // Mirror the review into a stub /feedback row so feedback-dependent
        // aggregates stay consistent for legacy /review-only clients. This does
        // NOT affect /complete (the gate reads completion confirmations only).
        // No-op if a real /feedback row already exists.
        $this->feedbackService->mirrorFromReview($collaboration, $reviewer, [
            'rating' => $legacyRating,
            'body' => $validated['body'] ?? null,
            'would_collaborate_again' => $validated['would_collaborate_again'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data' => $review,
        ], 201);
    }
}
