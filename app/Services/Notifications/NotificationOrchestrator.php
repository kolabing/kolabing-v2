<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Enums\NotificationPriority;
use App\Enums\NotificationType;
use App\Jobs\Notifications\SendPushNotificationJob;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Profile;
use App\Support\Notifications\NotificationMetrics;

class NotificationOrchestrator
{
    public function __construct(
        private readonly NotificationPayloadFactory $payloadFactory,
        private readonly NotificationMetrics $metrics,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(
        Profile $recipient,
        NotificationType $type,
        string $title,
        string $body,
        ?Profile $actor = null,
        ?string $targetId = null,
        ?string $targetType = null,
        ?NotificationPriority $priority = null,
        ?string $dedupeKey = null,
        ?string $deeplink = null,
        ?string $imageUrl = null,
        array $data = [],
        bool $inApp = true,
        bool $push = true,
    ): Notification {
        $preferences = $this->preferencesFor($recipient);
        $pushDeliveryEnabled = (bool) config('notifications.push_delivery_enabled', true);
        $target = $this->payloadFactory->target($type, $targetId, $targetType, $recipient, [
            'deeplink' => $deeplink,
        ]);
        $resolvedPriority = $priority ?? NotificationPriority::forType($type);
        $shouldPush = $push
            && $pushDeliveryEnabled
            && $preferences->allowsPushFor($type)
            && (! $preferences->isQuietHoursActive() || $type->ignoresQuietHours());
        $hasActiveTokens = $shouldPush
            && $recipient->deviceTokens()->where('is_active', true)->exists();

        $attributes = [
            'profile_id' => $recipient->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'actor_profile_id' => $actor?->id,
            'target_id' => $target->id,
            'target_type' => $target->type,
            'deeplink' => $target->deeplink,
            'image_url' => $imageUrl,
            'data' => $data,
            'priority' => $resolvedPriority,
            'is_in_app' => $inApp,
            'is_push' => $shouldPush,
            'dedupe_key' => $dedupeKey,
            'queued_at' => $hasActiveTokens ? now() : null,
        ];

        if ($dedupeKey !== null) {
            $notification = Notification::query()->firstOrCreate(
                [
                    'profile_id' => $recipient->id,
                    'dedupe_key' => $dedupeKey,
                ],
                $attributes,
            );
        } else {
            $notification = Notification::query()->create($attributes);
        }

        if ($notification->wasRecentlyCreated && $hasActiveTokens) {
            SendPushNotificationJob::dispatch($notification->id)->afterCommit();
        }

        if ($notification->wasRecentlyCreated) {
            $this->metrics->recordCreated($notification);

            if ($shouldPush && ! $hasActiveTokens) {
                $this->metrics->recordSkipped($type, 'no_active_tokens', [
                    'profile_id' => $recipient->id,
                    'inactive_token_count' => $recipient->deviceTokens()->where('is_active', false)->count(),
                ]);
            }
        }

        return $notification->fresh([
            'actorProfile.businessProfile',
            'actorProfile.communityProfile',
        ]);
    }

    private function preferencesFor(Profile $profile): NotificationPreference
    {
        return $profile->notificationPreferences()->firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'email_notifications' => true,
                'whatsapp_notifications' => true,
                'new_application_alerts' => true,
                'collaboration_updates' => true,
                'marketing_tips' => false,
                'messages_enabled' => true,
                'applications_enabled' => true,
                'collaborations_enabled' => true,
                'rewards_enabled' => true,
                'marketing_enabled' => false,
                'quiet_hours_start' => null,
                'quiet_hours_end' => null,
                'timezone' => null,
            ]
        );
    }
}
