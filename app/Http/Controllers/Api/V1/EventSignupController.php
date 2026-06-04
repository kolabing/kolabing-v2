<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\EventSignupStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EventResource;
use App\Http\Resources\Api\V1\EventSignupResource;
use App\Models\Event;
use App\Models\Profile;
use App\Services\ChatService;
use App\Services\EventSignupService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventSignupController extends Controller
{
    public function __construct(
        private readonly EventSignupService $signups,
        private readonly ChatService $chatService,
    ) {}

    /**
     * POST /api/v1/events/{event}/signup — join (or land on the waitlist).
     */
    public function store(Request $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $this->signups->signup($event, $profile);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => $this->messageFor($e->getMessage()),
            ], 422);
        }

        return $this->eventResponse($event);
    }

    /**
     * DELETE /api/v1/events/{event}/signup — leave/cancel (auto-promotes waitlist).
     */
    public function destroy(Request $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $this->signups->cancel($event, $profile);

        return $this->eventResponse($event);
    }

    /**
     * GET /api/v1/events/{event}/signups — going + waitlist roster (leader / can_manage).
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($event->community_id === null
            || ! $this->chatService->canManageCommunity($profile, $event->community_id)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this event\'s sign-ups.'),
            ], 403);
        }

        $rows = $event->signups()
            ->with('profile.businessProfile', 'profile.communityProfile')
            ->whereIn('status', [EventSignupStatus::Going->value, EventSignupStatus::Waitlisted->value])
            ->orderByRaw("CASE status WHEN 'going' THEN 0 ELSE 1 END")
            ->orderBy('waitlist_position')
            ->orderBy('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'going' => EventSignupResource::collection(
                    $rows->where('status', EventSignupStatus::Going)->values()
                ),
                'waitlist' => EventSignupResource::collection(
                    $rows->where('status', EventSignupStatus::Waitlisted)->values()
                ),
            ],
        ]);
    }

    private function eventResponse(Event $event): JsonResponse
    {
        $event->load('photos');

        return response()->json([
            'success' => true,
            'data' => new EventResource($event->fresh(['photos'])),
        ]);
    }

    private function messageFor(string $code): string
    {
        return match ($code) {
            'event_not_upcoming' => __('Sign-ups are only open for upcoming events.'),
            'event_not_signup_enabled' => __('This event is not open for sign-ups.'),
            'not_a_member' => __('You must be a member of this community to sign up.'),
            'tier_not_permitted' => __('Your tier is not permitted to sign up for this event.'),
            default => __('Could not complete the sign-up.'),
        };
    }
}
