<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Models\NotificationDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryFailedNotificationDeliveriesJob implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        NotificationDelivery::query()
            ->where('status', 'failed')
            ->where('attempt_count', '<', 3)
            ->latest('updated_at')
            ->get()
            ->pluck('notification_id')
            ->unique()
            ->each(static function (string $notificationId): void {
                SendPushNotificationJob::dispatch($notificationId);
            });
    }
}
