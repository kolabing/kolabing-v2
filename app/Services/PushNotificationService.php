<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Profile;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
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
    ): bool {
        return $this->sendToUsers([$recipient->id], $title, $body, $type, $targetId);
    }

    /**
     * Send a transactional push notification to one or more profile ids.
     *
     * @param  array<int, int|string>  $profileIds
     */
    public function sendToUsers(
        array $profileIds,
        string $title,
        string $body,
        NotificationType $type,
        ?string $targetId = null,
    ): bool {
        $response = $this->oneSignalService->sendPushToUsers(
            userIds: $profileIds,
            title: $title,
            body: $body,
            data: $this->buildDataPayload($type, $targetId),
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
     * @return array<string, string>
     */
    private function buildDataPayload(NotificationType $type, ?string $targetId): array
    {
        return [
            'type' => $type->value,
            'id' => $targetId ?? '',
            'deeplink' => $this->resolveDeeplink($type, $targetId),
        ];
    }

    private function resolveDeeplink(NotificationType $type, ?string $targetId): string
    {
        return match ($type) {
            NotificationType::NewMessage => $targetId ? "/chat/{$targetId}" : '/chat',
            NotificationType::ApplicationReceived,
            NotificationType::ApplicationAccepted,
            NotificationType::ApplicationDeclined => $targetId ? "/application/{$targetId}" : '/application',
            NotificationType::BadgeAwarded,
            NotificationType::GamificationBadgeEarned => '/badges',
            NotificationType::ChallengeVerified,
            NotificationType::RewardWon,
            NotificationType::PointsEarned,
            NotificationType::WithdrawalProcessed => '/me/rewards',
        };
    }
}
