<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\NotificationType;
use App\Models\Profile;
use App\Services\PushNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotification implements ShouldQueue
{
    use Queueable;

    /** @var int Maximum number of retry attempts */
    public int $tries = 3;

    /** @var int Seconds to wait before retrying */
    public int $backoff = 10;

    public function __construct(
        public readonly Profile $recipient,
        public readonly string $title,
        public readonly string $body,
        public readonly NotificationType $type,
        public readonly ?string $targetId = null,
    ) {}

    public function handle(PushNotificationService $pushService): void
    {
        $pushService->send(
            recipient: $this->recipient,
            title: $this->title,
            body: $this->body,
            type: $this->type,
            targetId: $this->targetId,
        );
    }
}
