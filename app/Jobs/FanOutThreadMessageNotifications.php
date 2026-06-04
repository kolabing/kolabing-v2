<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Services\ChatService;
use App\Services\NotificationService;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Notify the other members of a community/event chat thread about a new message:
 * one bulk in-app insert + one multi-recipient push, gated by each recipient's
 * `message_notifications` preference (default ON). Queued so a large audience
 * never blocks the message-send request. Collaboration threads are NOT handled
 * here (they notify via NotificationService::notifyNewMessage).
 */
class FanOutThreadMessageNotifications implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public readonly string $messageId,
        public readonly string $threadId,
        public readonly string $senderProfileId,
    ) {}

    public function handle(
        ChatService $chat,
        NotificationService $notifications,
        PushNotificationService $push,
    ): void {
        $thread = ChatThread::query()->find($this->threadId);
        $message = ChatMessage::query()
            ->with('senderProfile.businessProfile', 'senderProfile.communityProfile')
            ->find($this->messageId);

        if ($thread === null || $message === null) {
            return;
        }

        $recipientIds = $chat->threadRecipientIds($thread, $this->senderProfileId);
        $recipientIds = $notifications->recipientsAllowingMessages($recipientIds);

        if ($recipientIds === []) {
            return;
        }

        $title = $thread->name ?? __('New message');
        $senderName = $message->senderProfile?->businessProfile?->name
            ?? $message->senderProfile?->communityProfile?->name;
        $body = ($senderName !== null ? $senderName.': ' : '').Str::limit($message->content, 120);

        // In-app feed: one chunked bulk insert regardless of audience size.
        $notifications->recordNotifications(
            recipientIds: $recipientIds,
            type: NotificationType::NewMessage,
            title: $title,
            body: $body,
            actorProfileId: $this->senderProfileId,
            targetId: $thread->id,
            targetType: 'chat_thread',
        );

        // Push: one multi-recipient call. Best-effort — never fail the fan-out.
        try {
            $push->sendToUsers($recipientIds, $title, $body, NotificationType::NewMessage, $thread->id);
        } catch (Throwable $e) {
            Log::warning('Thread message push fan-out failed', [
                'thread_id' => $thread->id,
                'recipients' => count($recipientIds),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
