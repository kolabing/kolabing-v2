<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AcceptApplicationRequest;
use App\Http\Requests\Api\V1\ApplyToOpportunityRequest;
use App\Http\Requests\Api\V1\DeclineApplicationRequest;
use App\Http\Resources\Api\V1\ApplicationCollection;
use App\Http\Resources\Api\V1\ApplicationResource;
use App\Http\Resources\Api\V1\CollaborationResource;
use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Profile;
use App\Services\ApplicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applicationService
    ) {}

    /**
     * List applications for a specific opportunity.
     *
     * GET /api/v1/opportunities/{opportunity}/applications
     */
    public function forOpportunity(Request $request, CollabOpportunity $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('viewApplications', $opportunity)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view applications for this opportunity'),
            ], 403);
        }

        $filters = [
            'status' => $request->query('status'),
        ];

        $perPage = min((int) $request->query('per_page', 20), 100);

        $applications = $this->applicationService->getForOpportunity(
            $opportunity,
            $filters,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => new ApplicationCollection($applications),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    /**
     * Apply to an opportunity.
     *
     * POST /api/v1/opportunities/{opportunity}/applications
     */
    public function store(ApplyToOpportunityRequest $request, CollabOpportunity $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('create', [Application::class, $opportunity])) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to apply to this opportunity'),
            ], 403);
        }

        try {
            $application = $this->applicationService->apply(
                $profile,
                $opportunity,
                $request->validated()
            );

            $application->load([
                'applicantProfile.businessProfile',
                'applicantProfile.communityProfile',
                'collabOpportunity.creatorProfile',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Application submitted successfully'),
                'data' => new ApplicationResource($application),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Get application details.
     *
     * GET /api/v1/applications/{application}
     */
    public function show(Request $request, Application $application): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $application)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this application'),
            ], 403);
        }

        $application = $this->applicationService->findOrFail($application->id);

        return response()->json([
            'success' => true,
            'data' => new ApplicationResource($application),
        ]);
    }

    /**
     * Accept an application and create a collaboration.
     *
     * POST /api/v1/applications/{application}/accept
     */
    public function accept(AcceptApplicationRequest $request, Application $application): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('accept', $application)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to accept this application'),
            ], 403);
        }

        try {
            $result = $this->applicationService->accept($application, $request->validated());

            $result['application']->load([
                'applicantProfile.businessProfile',
                'applicantProfile.communityProfile',
                'collabOpportunity.creatorProfile',
            ]);

            $result['collaboration']->load([
                'creatorProfile',
                'applicantProfile',
                'businessProfile',
                'communityProfile',
                'collabOpportunity',
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'application' => new ApplicationResource($result['application']),
                    'collaboration' => new CollaborationResource($result['collaboration']),
                ],
                'message' => __('Application accepted and collaboration created'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        } catch (RuntimeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Decline an application.
     *
     * POST /api/v1/applications/{application}/decline
     */
    public function decline(DeclineApplicationRequest $request, Application $application): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('decline', $application)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to decline this application'),
            ], 403);
        }

        try {
            $validated = $request->validated();
            $application = $this->applicationService->decline(
                $application,
                $validated['reason'] ?? null
            );

            $application->load([
                'applicantProfile.businessProfile',
                'applicantProfile.communityProfile',
                'collabOpportunity.creatorProfile',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Application declined'),
                'data' => new ApplicationResource($application),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Withdraw own application.
     *
     * POST /api/v1/applications/{application}/withdraw
     */
    public function withdraw(Request $request, Application $application): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('withdraw', $application)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to withdraw this application'),
            ], 403);
        }

        try {
            $application = $this->applicationService->withdraw($application);

            $application->load([
                'applicantProfile.businessProfile',
                'applicantProfile.communityProfile',
                'collabOpportunity.creatorProfile',
            ]);

            return response()->json([
                'success' => true,
                'message' => __('Application withdrawn'),
                'data' => new ApplicationResource($application),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * List applications sent by authenticated user.
     *
     * GET /api/v1/me/applications
     */
    public function myApplications(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $filters = [
            'status' => $request->query('status'),
        ];

        $perPage = min((int) $request->query('per_page', 20), 100);

        $applications = $this->applicationService->getMyApplications(
            $profile,
            $filters,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => new ApplicationCollection($applications),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }

    /**
     * List applications received on user's opportunities.
     *
     * GET /api/v1/me/received-applications
     */
    public function receivedApplications(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $filters = [
            'status' => $request->query('status'),
            'opportunity_id' => $request->query('opportunity_id'),
        ];

        $perPage = min((int) $request->query('per_page', 20), 100);

        $applications = $this->applicationService->getReceivedApplications(
            $profile,
            $filters,
            $perPage
        );

        return response()->json([
            'success' => true,
            'data' => new ApplicationCollection($applications),
            'meta' => [
                'current_page' => $applications->currentPage(),
                'last_page' => $applications->lastPage(),
                'per_page' => $applications->perPage(),
                'total' => $applications->total(),
            ],
        ]);
    }
}
