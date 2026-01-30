<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NotificationResource;
use App\Models\Notification;
use App\Models\Profile;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    /**
     * Get paginated notifications for the authenticated user.
     *
     * GET /api/v1/me/notifications
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $perPage = min((int) $request->query('per_page', 20), 100);
        $notifications = $this->notificationService->getNotifications($profile, $perPage);

        return response()->json([
            'success' => true,
            'data' => NotificationResource::collection($notifications),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get the count of unread notifications.
     *
     * GET /api/v1/me/notifications/unread-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $count = $this->notificationService->getUnreadCount($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'count' => $count,
            ],
        ]);
    }

    /**
     * Mark a single notification as read.
     *
     * POST /api/v1/me/notifications/{notification}/read
     */
    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($notification->profile_id !== $profile->id) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to access this notification.'),
            ], 403);
        }

        $notification = $this->notificationService->markAsRead($notification);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $notification->id,
                'is_read' => true,
                'read_at' => $notification->read_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Mark all notifications as read for the authenticated user.
     *
     * POST /api/v1/me/notifications/read-all
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $count = $this->notificationService->markAllAsRead($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'updated_count' => $count,
            ],
        ]);
    }
}
