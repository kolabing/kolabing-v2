<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

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
use App\Services\CollaborationCompletionService;
use App\Services\CollaborationFeedbackService;
use App\Services\CollaborationService;
use App\Services\GamificationWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaborationController extends Controller
{
    public function __construct(
        private readonly CollaborationService $collaborationService,
        private readonly GamificationWalletService $gamificationService,
        private readonly CollaborationFeedbackService $feedbackService,
        private readonly CollaborationCompletionService $completionService,
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
     * - Collaboration must be completed
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

        // Reviews used to require completed status. Relaxed to active|completed
        // so a legacy client (which calls /review before /complete) can write a
        // review that the mirror promotes into a stub /feedback row — letting
        // the new gate succeed. Scheduled and cancelled collabs remain out of
        // scope.
        if (! ($collaboration->isCompleted() || $collaboration->isActive())) {
            return response()->json([
                'success' => false,
                'message' => 'Reviews can only be left for active or completed collaborations.',
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

        $review = CollaborationReview::create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewedProfileId,
            'reviewer_role' => $reviewerRole,
            'rating' => $validated['rating'],
            'note' => isset($validated['body']) ? mb_substr((string) $validated['body'], 0, 200) : null,
            'body' => $validated['body'] ?? null,
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

        // Mirror the review into a stub /feedback row so legacy clients still
        // satisfy the new /complete gate. No-op if a real /feedback row already
        // exists (post-mirror, post-new-app user).
        $this->feedbackService->mirrorFromReview($collaboration, $reviewer, [
            'rating' => $validated['rating'],
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
