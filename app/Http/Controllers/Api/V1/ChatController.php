<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ChatThreadType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SendChatMessageRequest;
use App\Http\Requests\Api\V1\StoreCommunityChatRequest;
use App\Http\Resources\Api\V1\ChatMessageCollection;
use App\Http\Resources\Api\V1\ChatMessageResource;
use App\Http\Resources\Api\V1\ChatThreadResource;
use App\Models\Application;
use App\Models\ChatThread;
use App\Models\Community;
use App\Models\Event;
use App\Models\Profile;
use App\Services\ChatService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ChatController extends Controller
{
    public function __construct(
        private readonly ChatService $chatService
    ) {}

    /**
     * List chat threads visible to the viewer (active-chats inbox).
     * Business viewers only see collaboration threads with ≥1 message.
     *
     * GET /api/v1/chats
     */
    public function chats(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        $threads = $this->chatService->visibleThreads($profile);

        return response()->json([
            'success' => true,
            'data' => ChatThreadResource::collection($threads),
        ]);
    }

    /**
     * Total unread messages across the viewer's visible threads (badge feed).
     *
     * GET /api/v1/chats/unread-count
     */
    public function unreadCountAcrossThreads(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $this->chatService->getUnreadCount($profile),
            ],
        ]);
    }

    /**
     * Create a custom community chat (owner / can_manage; ≤5 cap).
     *
     * POST /api/v1/communities/{community}/chats
     */
    public function storeCommunityChat(StoreCommunityChatRequest $request, Community $community): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($profile->cannot('manage', $community)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to manage this community.'),
            ], 403);
        }

        try {
            $thread = $this->chatService->createCustomChat($community, $profile, $request->validated()['name']);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => __('This community already has the maximum of 5 custom chats.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => new ChatThreadResource($thread),
        ], 201);
    }

    /**
     * Rename a custom community chat (manager only). Slug is preserved.
     *
     * PATCH /api/v1/chats/{thread}
     */
    public function updateCommunityChat(Request $request, ChatThread $thread): JsonResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:60']]);

        if (! $this->canManageCustomChat($request->user(), $thread)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to manage this chat.'),
            ], 403);
        }

        $thread = $this->chatService->renameCustomChat($thread, $validated['name']);

        return response()->json([
            'success' => true,
            'data' => new ChatThreadResource($thread),
        ]);
    }

    /**
     * Delete a custom community chat (manager only).
     *
     * DELETE /api/v1/chats/{thread}
     */
    public function destroyCommunityChat(Request $request, ChatThread $thread): JsonResponse
    {
        if (! $this->canManageCustomChat($request->user(), $thread)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to manage this chat.'),
            ], 403);
        }

        $this->chatService->deleteCustomChat($thread);

        return response()->json([
            'success' => true,
            'message' => __('Chat deleted.'),
        ]);
    }

    private function canManageCustomChat(Profile $profile, ChatThread $thread): bool
    {
        return $thread->type === ChatThreadType::CommunityCustom
            && $thread->community_id !== null
            && $this->chatService->canManageCommunity($profile, $thread->community_id);
    }

    /** Manager of the thread's community (any thread type with a community). */
    private function canManageThread(Profile $profile, ChatThread $thread): bool
    {
        return $thread->community_id !== null
            && $this->chatService->canManageCommunity($profile, $thread->community_id);
    }

    /**
     * Profile ids blocked from a thread (manager only).
     *
     * GET /api/v1/chats/{thread}/bans
     */
    public function bans(Request $request, ChatThread $thread): JsonResponse
    {
        if (! $this->canManageThread($request->user(), $thread)) {
            return response()->json(['success' => false, 'message' => __('Not authorized.')], 403);
        }

        return response()->json([
            'success' => true,
            'data' => ['banned_profile_ids' => $this->chatService->bannedProfileIds($thread)],
        ]);
    }

    /**
     * Block a member from a thread (manager only). POST /api/v1/chats/{thread}/bans
     */
    public function storeBan(Request $request, ChatThread $thread): JsonResponse
    {
        $validated = $request->validate([
            'profile_id' => ['required', 'uuid', 'exists:profiles,id'],
        ]);

        $profile = $request->user();
        if (! $this->canManageThread($profile, $thread)) {
            return response()->json(['success' => false, 'message' => __('Not authorized.')], 403);
        }

        $target = Profile::query()->findOrFail($validated['profile_id']);
        $this->chatService->banFromChat($thread, $target, $profile);

        return response()->json([
            'success' => true,
            'data' => ['banned_profile_ids' => $this->chatService->bannedProfileIds($thread)],
        ], 201);
    }

    /**
     * Lift a block (manager only). DELETE /api/v1/chats/{thread}/bans/{profile}
     */
    public function destroyBan(Request $request, ChatThread $thread, string $profile): JsonResponse
    {
        if (! $this->canManageThread($request->user(), $thread)) {
            return response()->json(['success' => false, 'message' => __('Not authorized.')], 403);
        }

        $this->chatService->unbanFromChat($thread, $profile);

        return response()->json([
            'success' => true,
            'data' => ['banned_profile_ids' => $this->chatService->bannedProfileIds($thread)],
        ]);
    }

    /**
     * Create (or fetch) the event chat for an event (leader / can_manage).
     *
     * POST /api/v1/events/{event}/chat
     */
    public function storeEventChat(Request $request, Event $event): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if ($event->community_id === null
            || ! $this->chatService->canManageCommunity($profile, $event->community_id)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to manage this event\'s chat.'),
            ], 403);
        }

        $thread = $this->chatService->eventThreadFor($event, $profile);

        return response()->json([
            'success' => true,
            'data' => new ChatThreadResource($thread),
        ], 201);
    }

    /**
     * Messages for a non-application thread (community/event), newest-last.
     *
     * GET /api/v1/chats/{thread}/messages
     */
    public function threadMessages(Request $request, ChatThread $thread): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->chatService->canAccessThread($profile, $thread)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this chat.'),
            ], 403);
        }

        $perPage = min((int) $request->query('per_page', 50), 100);
        $messages = $this->chatService->getThreadMessages($thread, $perPage);

        // Opening a thread marks it read for the viewer.
        $this->chatService->markThreadRead($profile, $thread);

        // Shape note: the app's generic chat client reads `data.messages`
        // (ChatService.getMessages → _list(data, 'messages')). The shared
        // ChatMessageCollection wraps under `data.data`, which that client can't
        // find — so return the list under `data.messages` explicitly. (The
        // application chat endpoint keeps the collection; it has its own parser.)
        return response()->json([
            'success' => true,
            'data' => [
                'messages' => ChatMessageResource::collection($messages->items()),
            ],
            'meta' => [
                'current_page' => $messages->currentPage(),
                'last_page' => $messages->lastPage(),
                'per_page' => $messages->perPage(),
                'total' => $messages->total(),
            ],
        ]);
    }

    /**
     * Send a message into a non-application thread.
     *
     * POST /api/v1/chats/{thread}/messages
     */
    public function storeThreadMessage(SendChatMessageRequest $request, ChatThread $thread): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->chatService->canAccessThread($profile, $thread)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to send messages in this chat.'),
            ], 403);
        }

        $message = $this->chatService->sendThreadMessage($profile, $thread, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Message sent successfully.'),
            'data' => new ChatMessageResource($message),
        ], 201);
    }

    /**
     * Move the viewer's read pointer for a thread.
     *
     * POST /api/v1/chats/{thread}/read
     */
    public function readThread(Request $request, ChatThread $thread): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->chatService->canAccessThread($profile, $thread)) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to access this chat.'),
            ], 403);
        }

        $this->chatService->markThreadRead($profile, $thread);

        return response()->json([
            'success' => true,
            'data' => null,
        ]);
    }

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
