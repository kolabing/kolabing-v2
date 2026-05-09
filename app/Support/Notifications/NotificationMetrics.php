<?php

declare(strict_types=1);

namespace App\Support\Notifications;

use App\Enums\NotificationType;
use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class NotificationMetrics
{
    public function recordCreated(Notification $notification): void
    {
        Log::info('notification.created', [
            'notification_id' => $notification->id,
            'profile_id' => $notification->profile_id,
            'type' => $notification->type->value,
            'is_push' => $notification->is_push,
            'is_in_app' => $notification->is_in_app,
            'dedupe_key' => $notification->dedupe_key,
            'unread_count' => Notification::query()
                ->where('profile_id', $notification->profile_id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function recordSkipped(NotificationType $type, string $reason, array $context = []): void
    {
        Log::info('notification.skipped', array_merge($context, [
            'type' => $type->value,
            'reason' => $reason,
        ]));
    }

    public function recordDelivery(Notification $notification, DeviceToken $deviceToken, string $status, ?string $error = null): void
    {
        Log::info('notification.delivery', [
            'notification_id' => $notification->id,
            'profile_id' => $notification->profile_id,
            'device_token_id' => $deviceToken->id,
            'type' => $notification->type->value,
            'status' => $status,
            'error' => $error,
        ]);
    }
}
