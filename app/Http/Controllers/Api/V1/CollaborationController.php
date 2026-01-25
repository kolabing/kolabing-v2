<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\CollaborationException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CancelCollaborationRequest;
use App\Http\Requests\Api\V1\CompleteCollaborationRequest;
use App\Http\Resources\Api\V1\CollaborationCollection;
use App\Http\Resources\Api\V1\CollaborationResource;
use App\Models\Collaboration;
use App\Models\Profile;
use App\Services\CollaborationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CollaborationController extends Controller
{
    public function __construct(
        private readonly CollaborationService $collaborationService
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
}
