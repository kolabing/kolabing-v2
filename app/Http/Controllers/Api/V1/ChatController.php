<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendChatMessageRequest;
use App\Http\Resources\Api\V1\ChatMessageCollection;
use App\Http\Resources\Api\V1\ChatMessageResource;
use App\Models\Application;
use App\Models\Profile;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    /**
     * Get chat messages for an application.
     *
     * GET /api/v1/applications/{application}/messages
     */
    public function index(Request $request, Application $application): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->chatService->canParticipate($profile, $application)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this chat.'),
            ], 403);
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $messages = $this->chatService->getMessages($application, $perPage);

        // Mark messages from other party as read
        $this->chatService->markMessagesAsRead($profile, $application);

        return response()->json([
            'success' => true,
            'data' => new ChatMessageCollection($messages),
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Send a new chat message.
     *
     * POST /api/v1/applications/{application}/messages
     */
    public function store(SendChatMessageRequest $request, Application $application): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        try {
            $message = $this->chatService->sendMessage(
                $profile,
                $application,
                $request->validated()
            );

            return response()->json([
                'success' => true,
                'message' => __('Message sent successfully.'),
                'data' => new ChatMessageResource($message),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 403);
        }
    }

    /**
     * Mark all messages in an application chat as read.
     *
     * POST /api/v1/applications/{application}/messages/read
     */
    public function markAsRead(Request $request, Application $application): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->chatService->canParticipate($profile, $application)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to access this chat.'),
            ], 403);
        }

        $count = $this->chatService->markMessagesAsRead($profile, $application);

        return response()->json([
            'success' => true,
            'message' => __(':count messages marked as read.', ['count' => $count]),
            'data' => [
                'marked_count' => $count,
            ],
        ]);
    }

    /**
     * Get unread message count for the authenticated user.
     *
     * GET /api/v1/me/unread-messages-count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $totalCount = $this->chatService->getUnreadCount($profile);
        $byApplication = $this->chatService->getUnreadCountByApplication($profile);

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $totalCount,
                'by_application' => $byApplication,
            ],
        ]);
    }
}
