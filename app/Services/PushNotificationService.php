<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Profile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private const IOS_CATEGORY_MESSAGES = 'kolabing_messages';

    private const IOS_CATEGORY_APPLICATIONS = 'kolabing_applications';

    private const IOS_CATEGORY_GENERAL = 'kolabing_general';

    public function __construct(
        private readonly OneSignalService $oneSignalService
    ) {}

    /**
     * Send a transactional push notification to a single profile via OneSignal.
     */
    public function send(
        Profile $recipient,
        string $title,
        string $body,
        NotificationType $type,
        ?string $targetId = null,
        array $messageOptions = [],
    ): bool {
        return $this->sendToUsers(
            [$recipient->id],
            $title,
            $body,
            $type,
            $targetId,
            $messageOptions,
            $recipient->notifications()->unread()->count(),
        );
    }

    /**
     * Send a transactional push notification to one or more profile ids.
     *
     * @param  array<int, int|string>  $profileIds
     * @param  array<string, mixed>  $messageOptions
     */
    public function sendToUsers(
        array $profileIds,
        string $title,
        string $body,
        NotificationType $type,
        ?string $targetId = null,
        array $messageOptions = [],
        ?int $badgeCount = null,
    ): bool {
        $payload = $this->buildPushPayload($type, $targetId, $badgeCount, $messageOptions);

        $response = $this->oneSignalService->sendPushToUsers(
            userIds: $profileIds,
            title: $title,
            body: $body,
            data: $payload['data'],
            messageOptions: Arr::except($payload, ['data']),
        );

        if (! array_key_exists('id', $response)) {
            Log::warning('OneSignal: no message created for transactional push', [
                'profile_ids' => $profileIds,
                'type' => $type->value,
                'target_id' => $targetId,
                'response' => $response,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $messageOptions
     * @return array<string, mixed>
     */
    private function buildPushPayload(
        NotificationType $type,
        ?string $targetId,
        ?int $badgeCount,
        array $messageOptions = [],
    ): array {
        $deeplink = $this->resolveDeeplink($type, $targetId);
        $subtitle = $this->resolveSubtitle($type);

        $payload = [
            'subtitle' => $subtitle,
            'ios_category' => $this->resolveIosCategory($type),
            'ios_interruption_level' => $this->resolveInterruptionLevel($type),
            'ios_relevance_score' => $this->resolveRelevanceScore($type),
            'buttons' => $this->resolveButtons($type),
            'data' => [
                'type' => $type->value,
                'id' => $targetId ?? '',
                'deeplink' => $deeplink,
                'subtitle' => $subtitle,
                'thread_id' => $this->resolveThreadId($type, $targetId),
                'action_deeplinks' => $this->resolveActionDeeplinks($type, $deeplink),
            ],
        ];

        if ($badgeCount !== null) {
            $payload['ios_badgeType'] = 'SetTo';
            $payload['ios_badgeCount'] = $badgeCount;
            $payload['data']['badge'] = $badgeCount;
        }

        return $this->mergePushOptions($payload, $messageOptions);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $messageOptions
     * @return array<string, mixed>
     */
    private function mergePushOptions(array $payload, array $messageOptions): array
    {
        if (isset($messageOptions['data']) && is_array($messageOptions['data'])) {
            $payload['data'] = array_replace_recursive($payload['data'], $messageOptions['data']);
        }

        foreach (Arr::except($messageOptions, ['data']) as $key => $value) {
            if ($value === null) {
                continue;
            }

            $payload[$key] = $value;
        }

        return $payload;
    }

    private function resolveDeeplink(NotificationType $type, ?string $targetId): string
    {
        return match ($type) {
            NotificationType::NewMessage,
            NotificationType::UnreadMessage => $targetId ? "/application/{$targetId}/chat" : '/notifications',
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined,
            NotificationType::ApplicationWithdrawn,
            NotificationType::ApplicationPending => $targetId ? "/application/{$targetId}" : '/notifications',
            NotificationType::KolabCreateIncomplete => $targetId ? "/kolabs/{$targetId}/edit" : '/kolabs',
            NotificationType::BadgeAwarded,
            NotificationType::GamificationBadgeEarned => '/badges',
            NotificationType::ChallengeVerified,
            NotificationType::RewardWon,
            NotificationType::PointsEarned,
            NotificationType::WithdrawalProcessed => '/me/rewards',
            NotificationType::CollaborationCreated,
            NotificationType::CollaborationActivated,
            NotificationType::CollaborationFeedbackReceived,
            NotificationType::CollaborationCompleted,
            NotificationType::CollaborationCancelled,
            NotificationType::CollabDayReminder,
            NotificationType::CollabFollowUpReminder => $targetId ? "/collaboration/{$targetId}" : '/notifications',
            NotificationType::WaitlistPromoted,
            NotificationType::EventCancelled,
            NotificationType::CommunityJoinRequested,
            NotificationType::CommunityJoinApproved,
            NotificationType::CommunityJoinDeclined,
            NotificationType::CommunityVerified,
            NotificationType::CommunityVerificationRejected => '/notifications',
            default => '/notifications',
        };
    }

    private function resolveSubtitle(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::NewMessage,
            NotificationType::UnreadMessage => 'Messages',
            NotificationType::ApplicationReceived => 'New application',
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined => 'Application update',
            NotificationType::ApplicationPending => 'Pending application',
            default => 'Kolabing',
        };
    }

    private function resolveIosCategory(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::NewMessage,
            NotificationType::UnreadMessage => self::IOS_CATEGORY_MESSAGES,
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined,
            NotificationType::ApplicationPending => self::IOS_CATEGORY_APPLICATIONS,
            default => self::IOS_CATEGORY_GENERAL,
        };
    }

    private function resolveInterruptionLevel(NotificationType $type): string
    {
        return match ($type) {
            NotificationType::NewMessage,
            NotificationType::UnreadMessage,
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined,
            NotificationType::ApplicationPending => 'active',
            default => 'passive',
        };
    }

    private function resolveRelevanceScore(NotificationType $type): float
    {
        return match ($type) {
            NotificationType::NewMessage,
            NotificationType::UnreadMessage => 0.9,
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined,
            NotificationType::ApplicationPending => 0.8,
            default => 0.5,
        };
    }

    /**
     * @return list<array{id: string, text: string}>
     */
    private function resolveButtons(NotificationType $type): array
    {
        return match ($type) {
            NotificationType::NewMessage,
            NotificationType::UnreadMessage => [
                ['id' => 'open_message_thread', 'text' => 'Open Chat'],
                ['id' => 'open_notifications', 'text' => 'Notifications'],
            ],
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined,
            NotificationType::ApplicationPending => [
                ['id' => 'view_application', 'text' => 'View Application'],
                ['id' => 'open_notifications', 'text' => 'Notifications'],
            ],
            default => [
                ['id' => 'open_app', 'text' => 'Open App'],
                ['id' => 'open_notifications', 'text' => 'Notifications'],
            ],
        };
    }

    private function resolveThreadId(NotificationType $type, ?string $targetId): string
    {
        return match ($type) {
            NotificationType::NewMessage,
            NotificationType::UnreadMessage => 'messages_'.($targetId ?? 'general'),
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined,
            NotificationType::ApplicationPending => 'applications_'.($targetId ?? 'general'),
            NotificationType::KolabCreateIncomplete => 'kolabs_'.($targetId ?? 'draft'),
            default => 'kolabing_general',
        };
    }

    /**
     * @return array<string, string>
     */
    private function resolveActionDeeplinks(NotificationType $type, string $deeplink): array
    {
        return match ($type) {
            NotificationType::NewMessage,
            NotificationType::UnreadMessage => [
                'open_message_thread' => $deeplink,
                'open_notifications' => '/notifications',
            ],
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined,
            NotificationType::ApplicationPending => [
                'view_application' => $deeplink,
                'open_notifications' => '/notifications',
            ],
            default => [
                'open_app' => $deeplink,
                'open_notifications' => '/notifications',
            ],
        };
    }
}
