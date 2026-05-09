<?php

declare(strict_types=1);

namespace App\Listeners\Messages;

use App\Events\Messages\MessageCreated;
use App\Models\Application;
use App\Models\ChatMessage;
use App\Services\NotificationService;

class CreateNewMessageNotification
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function handle(MessageCreated $event): void
    {
        $message = ChatMessage::query()->find($event->messageId);
        $application = Application::query()->find($event->applicationId);

        if ($message === null || $application === null) {
            return;
        }

        $this->notificationService->notifyNewMessage($message, $application);
    }
}
