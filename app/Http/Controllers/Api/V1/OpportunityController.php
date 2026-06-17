<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\IntentType;
use App\Exceptions\SubscriptionRequiredException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CreateOpportunityRequest;
use App\Http\Requests\Api\V1\UpdateOpportunityRequest;
use App\Http\Resources\Api\V1\KolabCollection;
use App\Http\Resources\Api\V1\KolabResource;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\KolabService;
use App\Services\OpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class OpportunityController extends Controller
{
    public function __construct(
        private readonly KolabService $kolabService,
        private readonly OpportunityService $opportunityService,
    ) {}

    /**
     * Browse published opportunities with filters.
     *
     * GET /api/v1/opportunities
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $filters = [
            'intent_type' => $request->query('intent_type'),
            'city' => $request->query('city'),
            'venue_type' => $request->query('venue_type'),
            'venue_mode' => $request->query('venue_mode'),
            'product_type' => $request->query('product_type'),
            'categories' => $request->query('categories'),
            'search' => $request->query('search'),
        ];
        $perPage = (int) $request->query('per_page', 20);
        $perPage = min(max($perPage, 1), 100);

        $kolabs = $this->kolabService->browse($profile, $filters, $perPage);

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
     * List opportunities created by the authenticated user.
     *
     * GET /api/v1/me/opportunities
     */
    public function myOpportunities(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($this->opportunityService->hasReachedFreemiumCollabLimit($profile)) {
            return response()->json([
                'success' => false,
                'message' => __('A subscription is required to create more opportunities.'),
                'requires_subscription' => true,
                'code' => 'subscription_required',
            ], 402);
        }

        $filters = [
            'status' => $request->query('status'),
        ];

        $perPage = (int) $request->query('per_page', 20);
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
     * Get a single opportunity.
     *
     * GET /api/v1/opportunities/{opportunity}
     */
    public function show(Request $request, string $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $kolab = Kolab::query()->findOrFail($opportunity);

        if ($profile->cannot('view', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this opportunity.'),
            ], 403);
        }

        $kolab->load([
            'creatorProfile' => function ($query) {
                $query->with([
                    'events' => function ($q) {
                        $q->orderByDesc('event_date')->limit(5);
                    },
                    'events.photos' => function ($q) {
                        $q->orderBy('sort_order')->limit(10);
                    },
                    'galleryPhotos' => function ($q) {
                        $q->orderBy('sort_order')->limit(10);
                    },
                ]);
            },
            'applications',
        ]);

        return response()->json([
            'success' => true,
            'data' => new KolabResource($kolab),
        ]);
    }

    /**
     * Create a new opportunity.
     *
     * POST /api/v1/opportunities
     */
    public function store(CreateOpportunityRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $kolab = $this->kolabService->create(
                $profile,
                $this->mapLegacyOpportunityPayload($request->validated())
            );
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Opportunity created successfully'),
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
     * Update an opportunity.
     *
     * PUT /api/v1/opportunities/{opportunity}
     */
    public function update(UpdateOpportunityRequest $request, string $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $kolab = Kolab::query()->findOrFail($opportunity);

        if ($profile->cannot('update', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to update this opportunity.'),
            ], 403);
        }

        try {
            $kolab = $this->kolabService->update(
                $kolab,
                $this->mapLegacyOpportunityPayload($request->validated(), partial: true)
            );
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Opportunity updated successfully'),
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
     * Delete an opportunity.
     *
     * DELETE /api/v1/opportunities/{opportunity}
     */
    public function destroy(Request $request, string $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $kolab = Kolab::query()->findOrFail($opportunity);

        if ($profile->cannot('delete', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to delete this opportunity.'),
            ], 403);
        }

        try {
            $this->kolabService->delete($kolab);

            return response()->json([
                'success' => true,
                'message' => __('Opportunity deleted successfully'),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Publish a draft opportunity.
     *
     * POST /api/v1/opportunities/{opportunity}/publish
     */
    public function publish(Request $request, string $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $kolab = Kolab::query()->findOrFail($opportunity);

        if ($profile->cannot('publish', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to publish this opportunity.'),
            ], 403);
        }

        if ($profile->isBusiness() && ! $profile->hasActiveSubscription()) {
            return response()->json([
                'success' => false,
                'message' => __('Business users must have an active subscription to publish opportunities.'),
                'requires_subscription' => true,
                'code' => 'subscription_required',
            ], 402);
        }

        try {
            $kolab = $this->kolabService->publish($kolab);
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Opportunity published successfully'),
                'data' => new KolabResource($kolab),
            ]);
        } catch (SubscriptionRequiredException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'requires_subscription' => true,
                'code' => 'subscription_required',
            ], 402);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Close a published opportunity.
     *
     * POST /api/v1/opportunities/{opportunity}/close
     */
    public function close(Request $request, string $opportunity): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $kolab = Kolab::query()->findOrFail($opportunity);

        if ($profile->cannot('close', $kolab)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to close this opportunity.'),
            ], 403);
        }

        try {
            $kolab = $this->kolabService->close($kolab);
            $kolab->load('creatorProfile');

            return response()->json([
                'success' => true,
                'message' => __('Opportunity closed successfully'),
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
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mapLegacyOpportunityPayload(array $data, bool $partial = false): array
    {
        $mapped = [];

        if (! $partial || array_key_exists('title', $data)) {
            $mapped['title'] = $data['title'] ?? null;
        }
        if (! $partial || array_key_exists('description', $data)) {
            $mapped['description'] = $data['description'] ?? null;
        }

        $mapped['intent_type'] = IntentType::CommunitySeeking->value;

        if (! $partial || array_key_exists('preferred_city', $data)) {
            $mapped['preferred_city'] = $data['preferred_city'] ?? null;
        }
        if (array_key_exists('categories', $data)) {
            $mapped['community_types'] = $data['categories'];
        }
        if (array_key_exists('business_offer', $data)) {
            $mapped['offers_in_return'] = $data['business_offer'];
        }
        if (array_key_exists('community_deliverables', $data)) {
            $mapped['needs'] = $data['community_deliverables'];
        }
        if (array_key_exists('venue_mode', $data)) {
            $mapped['venue_preference'] = match ($data['venue_mode']) {
                'business_venue' => 'business_provides',
                'community_venue' => 'community_provides',
                default => 'no_venue',
            };
        }
        if (array_key_exists('address', $data)) {
            $mapped['venue_address'] = $data['address'];
        }
        if (array_key_exists('offer_photo', $data)) {
            $mapped['media'] = $data['offer_photo'] === null
                ? null
                : [[
                    'url' => $data['offer_photo'],
                    'type' => 'image',
                    'thumbnail_url' => null,
                    'sort_order' => 0,
                ]];
        }

        foreach (['availability_mode', 'availability_start', 'availability_end', 'selected_time', 'recurring_days'] as $field) {
            if (array_key_exists($field, $data)) {
                $mapped[$field] = $data[$field];
            }
        }

        return array_filter($mapped, static fn (mixed $value): bool => $value !== null);
    }
}
