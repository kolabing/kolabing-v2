<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Data\Notifications\NotificationTargetData;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Profile;

class NotificationPayloadFactory
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function target(
        NotificationType $type,
        ?string $targetId,
        ?string $targetType,
        ?Profile $recipient = null,
        array $context = [],
    ): NotificationTargetData {
        $deeplink = match ($type) {
            NotificationType::NewMessage => $targetId !== null
                ? "/application/{$targetId}/chat"
                : '/notifications',
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined => $targetId !== null
                ? "/application/{$targetId}"
                : '/notifications',
            NotificationType::CollaborationScheduled,
            NotificationType::CollaborationRescheduled,
            NotificationType::CollaborationCancelled,
            NotificationType::CollaborationReminder24h,
            NotificationType::CollaborationReminderSameDay => $targetId !== null
                ? "/collaboration/{$targetId}"
                : '/notifications',
            NotificationType::WithdrawalApproved,
            NotificationType::WithdrawalRejected,
            NotificationType::WithdrawalPaid => '/community/wallet',
            NotificationType::ReferralRewardEarned => $recipient?->isBusiness()
                ? '/business/referrals'
                : '/community/referrals',
            default => $context['deeplink'] ?? '/notifications',
        };

        return new NotificationTargetData(
            id: $targetId,
            type: $targetType,
            deeplink: $deeplink,
        );
    }

    /**
     * @return array<string, string>
     */
    public function toPushData(Notification $notification): array
    {
        $data = [
            'notification_id' => $notification->id,
            'type' => $notification->type->value,
            'id' => $notification->target_id ?? $notification->id,
            'target_type' => $notification->target_type ?? '',
            'target_id' => $notification->target_id ?? '',
            'deeplink' => $notification->deeplink ?? '/notifications',
            'actor_id' => $notification->actor_profile_id ?? '',
            'actor_name' => $notification->actorProfile?->getExtendedProfile()?->name ?? '',
            'title' => $notification->title,
            'body' => $notification->body,
            'image_url' => $notification->image_url ?? '',
            'priority' => $notification->priority->value,
            'dedupe_key' => $notification->dedupe_key ?? '',
            'sent_at' => ($notification->queued_at ?? $notification->created_at ?? now())->toIso8601String(),
        ];

        if (is_array($notification->data)) {
            foreach ($notification->data as $key => $value) {
                if (! is_scalar($value) || $value === null || isset($data[$key])) {
                    continue;
                }

                $data[$key] = (string) $value;
            }
        }

        return array_filter($data, static fn (string $value): bool => $value !== '');
    }
}
