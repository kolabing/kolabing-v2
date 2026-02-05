<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Http\Requests\Api\V1\UpdateEventRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Event;
use App\Models\Profile;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService
    ) {}

    /**
     * List events for a profile.
     *
     * GET /api/v1/events?profile_id={uuid}
     * Defaults to authenticated user if profile_id not provided.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $authProfile */
        $authProfile = $request->user();

        $profileId = $request->query('profile_id');
        $perPage = min((int) $request->query('limit', '10'), 50);

        if ($profileId) {
            $profile = Profile::query()->findOrFail($profileId);
        } else {
            $profile = $authProfile;
        }

        $paginator = $this->eventService->listForProfile($profile, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'events' => EventResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }

    /**
     * Get a single event.
     *
     * GET /api/v1/events/{event}
     */
    public function show(Request $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('view', $event)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this event.'),
            ], 403);
        }

        $event = $this->eventService->getWithRelations($event);

        return response()->json([
            'success' => true,
            'data' => new EventResource($event),
        ]);
    }

    /**
     * Create a new event.
     *
     * POST /api/v1/events
     */
    public function store(StoreEventRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('create', Event::class)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to create events.'),
            ], 403);
        }

        $event = $this->eventService->create(
            $profile,
            $request->validated(),
            $request->file('photos')
        );

        return response()->json([
            'success' => true,
            'message' => __('Event created successfully.'),
            'data' => new EventResource($event),
        ], 201);
    }

    /**
     * Update an event.
     *
     * PUT /api/v1/events/{event}
     */
    public function update(UpdateEventRequest $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $event)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to update this event.'),
            ], 403);
        }

        $event = $this->eventService->update($event, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Event updated successfully.'),
            'data' => new EventResource($event),
        ]);
    }

    /**
     * Delete an event.
     *
     * DELETE /api/v1/events/{event}
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('delete', $event)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to delete this event.'),
            ], 403);
        }

        $this->eventService->delete($event);

        return response()->json([
            'success' => true,
            'message' => __('Event deleted successfully.'),
        ]);
    }
}
