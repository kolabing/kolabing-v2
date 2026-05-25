<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PointEventType;
use App\Exceptions\CollaborationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelCollaborationRequest;
use App\Http\Requests\Api\V1\CompleteCollaborationRequest;
use App\Http\Requests\Api\V1\StoreCollaborationReviewRequest;
use App\Http\Resources\Api\V1\CollaborationCollection;
use App\Http\Resources\Api\V1\CollaborationResource;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Profile;
use App\Services\CollaborationService;
use App\Services\GamificationWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaborationController extends Controller
{
    public function __construct(
        private readonly CollaborationService $collaborationService,
        private readonly GamificationWalletService $gamificationService,
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
    public function activate(Collaboration $collaboration): JsonResponse
    {
        $this->authorize('activate', $collaboration);

        try {
            $collaboration = $this->collaborationService->activate($collaboration);
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

        $validated = $request->validated();

        try {
            $collaboration = $this->collaborationService->complete(
                $collaboration,
                $validated['feedback'] ?? null
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
            'message' => __('collaboration.completed'),
            'data' => new CollaborationResource($collaboration),
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

        try {
            $collaboration = $this->collaborationService->cancel(
                $collaboration,
                $validated['reason']
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

        if (! $collaboration->isCompleted()) {
            return response()->json([
                'success' => false,
                'message' => 'Reviews can only be left for completed collaborations.',
                'error_code' => 'collaboration_not_completed',
            ], 422);
        }

        // Determine which profile is being reviewed (the OTHER party).
        // business_profile_id / community_profile_id are dedicated FK columns
        // that are always set regardless of creator/applicant assignment order.
        $reviewedProfileId = $reviewer->id === $collaboration->business_profile_id
            ? $collaboration->community_profile_id
            : $collaboration->business_profile_id;

        if ($reviewedProfileId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Could not determine the profile to be reviewed.',
                'error_code' => 'missing_participant',
            ], 422);
        }

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
            'rating' => $validated['rating'],
            'body' => $validated['body'] ?? null,
            'would_collaborate_again' => $validated['would_collaborate_again'] ?? null,
        ]);

        // Award XP once for leaving a review — uses existing ReviewPosted event type.
        $this->gamificationService->awardPoints(
            $reviewer->id,
            PointEventType::ReviewPosted->defaultPoints(),
            PointEventType::ReviewPosted,
            $collaboration->id,
            'Left a review for a completed Kolab',
        );

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully.',
            'data' => $review,
        ], 201);
    }
}
