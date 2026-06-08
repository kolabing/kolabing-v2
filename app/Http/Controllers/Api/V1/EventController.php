<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventRequest;
use App\Http\Requests\Api\V1\UpdateEventRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Community;
use App\Models\Event;
use App\Models\Profile;
use App\Services\EventSeriesService;
use App\Services\EventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $eventService,
        private readonly EventSeriesService $eventSeriesService,
    ) {}

    /**
     * List events, one lifecycle for every surface (NF-CHAT Phase 3 §6.1).
     *
     * GET /api/v1/events?community_id=&time=upcoming|past&attendee=me&profile_id=
     * Filters: community_id (community Details), time (upcoming tab vs past
     * showcase), attendee=me (profile's attended/going), profile_id (a profile's
     * created events). With no filter, defaults to the authenticated user's own.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $authProfile */
        $authProfile = $request->user();

        $perPage = min((int) $request->query('limit', '10'), 50);

        $communityId = $request->query('community_id');
        $profileId = $request->query('profile_id');
        $time = $request->query('time'); // upcoming | past | null
        $attendeeId = $request->query('attendee') === 'me' ? $authProfile->id : null;

        $filters = array_filter([
            'community_id' => $communityId,
            'profile_id' => $profileId,
            'attendee_profile_id' => $attendeeId,
            'time' => $time,
        ], static fn ($value) => $value !== null);

        // Back-compat: with no scoping filter, list the viewer's own events.
        if ($communityId === null && $profileId === null && $attendeeId === null) {
            $filters['profile_id'] = $authProfile->id;
        }

        $paginator = $this->eventService->list($filters, $perPage);

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

        $data = $request->validated();

        if ($request->isUpcoming()) {
            // Upcoming community event: must be owner / can_manage of the community.
            $community = Community::query()->find($data['community_id']);
            if ($community === null || $profile->cannot('manage', $community)) {
                return response()->json([
                    'success' => false,
                    'message' => __('You are not authorized to create events for this community.'),
                ], 403);
            }
        } elseif ($profile->cannot('create', Event::class)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to create events.'),
            ], 403);
        }

        // Recurring create → build a series and return its first occurrence (so
        // the app's create flow gets an event back, now carrying series_id).
        if ($request->isUpcoming() && $request->filled('recurrence')) {
            $series = $this->eventSeriesService->create($profile, $data);
            $first = $series->events()->first();

            return response()->json([
                'success' => true,
                'message' => __('Recurring event created successfully.'),
                'data' => $first !== null
                    ? new EventResource($this->eventService->getWithRelations($first))
                    : null,
                'series_id' => $series->id,
                'occurrences_created' => $series->events()->count(),
            ], 201);
        }

        $event = $this->eventService->create(
            $profile,
            $data,
            $request->file('photos') ?? []
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

        $data = $request->validated();
        $scope = $request->query('scope', 'this');
        if (! in_array($scope, ['this', 'following', 'series'], true)) {
            $scope = 'this';
        }

        // Edit → make recurring: apply field edits, then convert to a series.
        if ($event->series_id === null && $request->filled('recurrence')) {
            $fields = array_diff_key($data, ['recurrence' => true]);
            if ($fields !== []) {
                $this->eventService->update($event, $fields);
            }
            $series = $this->eventSeriesService->convertToSeries($event->fresh(), $data['recurrence']);

            return response()->json([
                'success' => true,
                'message' => __('Recurring series created from this event.'),
                'data' => new EventResource($this->eventService->getWithRelations($event->fresh())),
                'series_id' => $series->id,
                'occurrences_created' => $series->events()->count(),
            ]);
        }

        // Scoped edit across a series.
        if ($event->series_id !== null && $scope !== 'this') {
            $count = $this->eventSeriesService->updateScope($event, $data, $scope);

            return response()->json([
                'success' => true,
                'message' => __('Event updated successfully.'),
                'data' => new EventResource($this->eventService->getWithRelations($event->fresh())),
                'updated_count' => $count,
            ]);
        }

        $event = $this->eventService->update($event, $data);

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

        // scope = this (default) | following | series — only meaningful when the
        // event belongs to a recurring series.
        $scope = $request->query('scope', 'this');
        if (! in_array($scope, ['this', 'following', 'series'], true)) {
            $scope = 'this';
        }

        $deleted = $this->eventSeriesService->deleteScope($event, $scope);

        return response()->json([
            'success' => true,
            'message' => __('Event deleted successfully.'),
            'deleted_count' => $deleted,
        ]);
    }

    /**
     * Extend a recurring series' rolling window (materialise the next months).
     *
     * POST /api/v1/event-series/{series}/extend
     */
    public function extendSeries(Request $request, \App\Models\EventSeries $series): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $community = Community::query()->find($series->community_id);
        if ($community === null || $profile->cannot('manage', $community)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to manage this series.'),
            ], 403);
        }

        $created = $this->eventSeriesService->extend($series);

        return response()->json([
            'success' => true,
            'message' => __('Series extended.'),
            'occurrences_created' => $created,
        ]);
    }
}
