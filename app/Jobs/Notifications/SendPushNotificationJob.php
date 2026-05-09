<?php

declare(strict_types=1);

namespace App\Jobs\Notifications;

use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Services\Notifications\DeviceTokenService;
use App\Services\Notifications\FcmClient;
use App\Services\Notifications\NotificationPayloadFactory;
use App\Support\Notifications\NotificationMetrics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 90];
    }

    public function __construct(
        public readonly string $notificationId
    ) {}

    public function handle(
        FcmClient $fcmClient,
        NotificationPayloadFactory $payloadFactory,
        DeviceTokenService $deviceTokenService,
        NotificationMetrics $metrics,
    ): void {
        $notification = Notification::query()
            ->with([
                'profile.deviceTokens',
                'actorProfile.businessProfile',
                'actorProfile.communityProfile',
                'deliveries',
            ])
            ->find($this->notificationId);

        if ($notification === null || ! $notification->is_push) {
            return;
        }

        $tokens = $notification->profile->deviceTokens
            ->where('is_active', true)
            ->values();

        if ($tokens->isEmpty()) {
            return;
        }

        $payload = $payloadFactory->toPushData($notification);
        $transientFailure = null;

        foreach ($tokens as $token) {
            $delivery = NotificationDelivery::query()->firstOrCreate(
                [
                    'notification_id' => $notification->id,
                    'device_token_id' => $token->id,
                ],
                [
                    'provider' => 'fcm',
                    'status' => 'queued',
                    'attempt_count' => 0,
                ],
            );

            if (in_array($delivery->status, ['sent', 'invalid_token'], true)) {
                continue;
            }

            $delivery->increment('attempt_count');
            $delivery->refresh();

            try {
                $providerMessageId = $fcmClient->send($token, $notification->title, $notification->body, $payload);

                $delivery->update([
                    'status' => 'sent',
                    'provider_message_id' => $providerMessageId,
                    'last_error_code' => null,
                    'last_error_message' => null,
                    'delivered_at' => now(),
                ]);

                $token->update([
                    'last_delivered_at' => now(),
                ]);

                $metrics->recordDelivery($notification, $token, 'sent');
            } catch (\Throwable $exception) {
                if ($fcmClient->isInvalidToken($exception)) {
                    $delivery->update([
                        'status' => 'invalid_token',
                        'last_error_code' => 'invalid_token',
                        'last_error_message' => $exception->getMessage(),
                    ]);

                    $deviceTokenService->markInvalid($token, 'invalid_token');
                    $metrics->recordDelivery($notification, $token, 'invalid_token', $exception->getMessage());

                    continue;
                }

                $delivery->update([
                    'status' => 'failed',
                    'last_error_code' => class_basename($exception),
                    'last_error_message' => $exception->getMessage(),
                ]);

                $metrics->recordDelivery($notification, $token, 'failed', $exception->getMessage());
                $transientFailure ??= $exception;
            }
        }

        if ($transientFailure !== null) {
            Log::warning('Push delivery failed and will be retried', [
                'notification_id' => $notification->id,
                'error' => $transientFailure->getMessage(),
            ]);

            throw $transientFailure;
        }
    }
}
