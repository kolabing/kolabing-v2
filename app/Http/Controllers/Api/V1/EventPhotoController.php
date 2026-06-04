<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventPhotosRequest;
use App\Http\Resources\Api\V1\EventResource;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Profile;
use App\Services\EventService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventPhotoController extends Controller
{
    public function __construct(
        private readonly EventService $eventService
    ) {}

    /**
     * POST /api/v1/events/{event}/photos — add photos (creator / community can_manage).
     */
    public function store(StoreEventPhotosRequest $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->canManageEvent($profile, $event)) {
            return $this->forbidden();
        }

        try {
            $event = $this->eventService->addPhotos($event, $request->file('photos'));
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => __('An event can have at most 5 photos.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new EventResource($event),
        ], 201);
    }

    /**
     * DELETE /api/v1/events/{event}/photos/{photo} — remove a photo (creator / can_manage).
     */
    public function destroy(Request $request, Event $event, EventPhoto $photo): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->canManageEvent($profile, $event)) {
            return $this->forbidden();
        }

        if ($photo->event_id !== $event->id) {
            return response()->json([
                'success' => false,
                'message' => __('Photo not found for this event.'),
            ], 404);
        }

        $this->eventService->deletePhoto($photo);

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

    /**
     * The event creator, or an owner/can_manage of the event's community.
     */
    private function canManageEvent(Profile $profile, Event $event): bool
    {
        if ($profile->id === $event->profile_id) {
            return true;
        }

        if ($event->community_id !== null) {
            $community = Community::query()->find($event->community_id);

            return $community !== null && $profile->can('manage', $community);
        }

        return false;
    }

    private function forbidden(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => __('You are not authorized to manage this event\'s photos.'),
        ], 403);
    }
}
