<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreEventRewardRequest;
use App\Http\Requests\Api\V1\UpdateEventRewardRequest;
use App\Http\Resources\Api\V1\EventRewardResource;
use App\Models\Event;
use App\Models\EventReward;
use App\Models\Profile;
use App\Services\EventRewardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventRewardController extends Controller
{
    public function __construct(
        private readonly EventRewardService $eventRewardService
    ) {}

    /**
     * List all rewards for an event.
     *
     * GET /api/v1/events/{event}/rewards
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $rewards = $this->eventRewardService->listForEvent($event);

        return response()->json([
            'success' => true,
            'data' => EventRewardResource::collection($rewards),
        ]);
    }

    /**
     * Create a new reward for an event.
     *
     * POST /api/v1/events/{event}/rewards
     */
    public function store(StoreEventRewardRequest $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('create', [EventReward::class, $event])) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to create rewards for this event.'),
            ], 403);
        }

        $reward = $this->eventRewardService->create($event, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Reward created successfully.'),
            'data' => new EventRewardResource($reward),
        ], 201);
    }

    /**
     * Update an existing reward.
     *
     * PUT /api/v1/event-rewards/{eventReward}
     */
    public function update(UpdateEventRewardRequest $request, EventReward $eventReward): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('update', $eventReward)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to update this reward.'),
            ], 403);
        }

        $reward = $this->eventRewardService->update($eventReward, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Reward updated successfully.'),
            'data' => new EventRewardResource($reward),
        ]);
    }

    /**
     * Delete a reward.
     *
     * DELETE /api/v1/event-rewards/{eventReward}
     */
    public function destroy(Request $request, EventReward $eventReward): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('delete', $eventReward)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to delete this reward.'),
            ], 403);
        }

        try {
            $this->eventRewardService->delete($eventReward);
        } catch (\LogicException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'message' => __('Reward deleted successfully.'),
        ]);
    }
}
