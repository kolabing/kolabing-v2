<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckinRequest;
use App\Http\Resources\Api\V1\EventCheckinResource;
use App\Models\Event;
use App\Models\Profile;
use App\Services\CheckinService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckinController extends Controller
{
    public function __construct(
        private readonly CheckinService $checkinService
    ) {}

    /**
     * Generate a QR check-in token for an event.
     *
     * POST /api/v1/events/{event}/generate-qr
     */
    public function generateQr(Request $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->id !== $event->profile_id) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to generate a QR token for this event.'),
            ], 403);
        }

        $token = $this->checkinService->generateCheckinToken($event);

        return response()->json([
            'success' => true,
            'data' => [
                'checkin_token' => $token,
            ],
        ]);
    }

    /**
     * Check in an attendee using a QR token.
     *
     * POST /api/v1/checkin
     */
    public function checkin(CheckinRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $checkin = $this->checkinService->checkin($profile, $request->validated('token'));

            return response()->json([
                'success' => true,
                'message' => __('Checked in successfully.'),
                'data' => new EventCheckinResource($checkin),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        } catch (\LogicException $e) {
            $statusCode = str_contains($e->getMessage(), 'already checked in') ? 409 : 422;

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], $statusCode);
        }
    }

    /**
     * List check-ins for an event.
     *
     * GET /api/v1/events/{event}/checkins
     */
    public function index(Request $request, Event $event): JsonResponse
    {
        $perPage = min((int) $request->query('limit', '10'), 50);

        $paginator = $this->checkinService->getCheckins($event, $perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'checkins' => EventCheckinResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'total_pages' => $paginator->lastPage(),
                    'total_count' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                ],
            ],
        ]);
    }
}
