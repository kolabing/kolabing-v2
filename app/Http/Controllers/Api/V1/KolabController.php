<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateKolabRequest;
use App\Http\Requests\Api\V1\UpdateKolabRequest;
use App\Http\Resources\Api\V1\KolabCollection;
use App\Http\Resources\Api\V1\KolabResource;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\KolabService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class KolabController extends Controller
{
    public function __construct(
        private readonly KolabService $kolabService,
    ) {}

    /**
     * Browse published kolabs with filters.
     *
     * GET /api/v1/kolabs
     */
    public function index(Request $request): JsonResponse
    {
        $filters = [
            'intent_type' => $request->query('intent_type'),
            'city' => $request->query('city'),
            'venue_type' => $request->query('venue_type'),
            'product_type' => $request->query('product_type'),
            'needs' => $request->query('needs'),
            'community_types' => $request->query('community_types'),
            'search' => $request->query('search'),
        ];

        $perPage = (int) $request->query('per_page', 15);
        $perPage = min(max($perPage, 1), 100);

        $kolabs = $this->kolabService->browse($filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => new KolabCollection($kolabs),
            'meta' => [
                'current_page' => $kolabs->currentPage(),
                'last_page' => $kolabs->lastPage(),
                'per_page' => $kolabs->perPage(),
                'total' => $kolabs->total(),
            ],
        ]);
    }

    /**
     * List kolabs created by the authenticated user.
     *
     * GET /api/v1/kolabs/me
     */
    public function myKolabs(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $filters = [
            'status' => $request->query('status'),
        ];

        $perPage = (int) $request->query('per_page', 10);
        $perPage = min(max($perPage, 1), 100);

        $kolabs = $this->kolabService->getMyKolabs($profile, $filters, $perPage);

        return response()->json([
            'success' => true,
            'data' => new KolabCollection($kolabs),
            'meta' => [
                'current_page' => $kolabs->currentPage(),
                'last_page' => $kolabs->lastPage(),
                'per_page' => $kolabs->perPage(),
                'total' => $kolabs->total(),
            ],
        ]);
    }

    /**
     * Create a new kolab.
     *
     * POST /api/v1/kolabs
     */
    public function store(CreateKolabRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('create', Kolab::class)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to create kolabs.'),
            ], 403);
        }

        try {
            $kolab = $this->kolabService->create($profile, $request->validated());
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Kolab created successfully.'),
                'data' => new KolabResource($kolab),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get a single kolab.
     *
     * GET /api/v1/kolabs/{kolab}
     */
    public function show(Request $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this kolab.'),
            ], 403);
        }

        $kolab->load('creatorProfile');

        return response()->json([
            'success' => true,
            'data' => new KolabResource($kolab),
        ]);
    }

    /**
     * Update a kolab.
     *
     * PUT /api/v1/kolabs/{kolab}
     */
    public function update(UpdateKolabRequest $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to update this kolab.'),
            ], 403);
        }

        try {
            $kolab = $this->kolabService->update($kolab, $request->validated());
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Kolab updated successfully.'),
                'data' => new KolabResource($kolab),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Delete a kolab.
     *
     * DELETE /api/v1/kolabs/{kolab}
     */
    public function destroy(Request $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('delete', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to delete this kolab.'),
            ], 403);
        }

        try {
            $this->kolabService->delete($kolab);

            return response()->json([
                'success' => true,
                'message' => __('Kolab deleted successfully.'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Publish a draft kolab.
     *
     * POST /api/v1/kolabs/{kolab}/publish
     */
    public function publish(Request $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('publish', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to publish this kolab.'),
            ], 403);
        }

        try {
            $kolab = $this->kolabService->publish($kolab);
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Kolab published successfully.'),
                'data' => new KolabResource($kolab),
            ]);
        } catch (InvalidArgumentException $e) {
            if (str_contains($e->getMessage(), 'subscription')) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'requires_subscription' => true,
                    'code' => 'subscription_required',
                ], 402);
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Close a published kolab.
     *
     * POST /api/v1/kolabs/{kolab}/close
     */
    public function close(Request $request, Kolab $kolab): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('close', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to close this kolab.'),
            ], 403);
        }

        try {
            $kolab = $this->kolabService->close($kolab);
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Kolab closed successfully.'),
                'data' => new KolabResource($kolab),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
