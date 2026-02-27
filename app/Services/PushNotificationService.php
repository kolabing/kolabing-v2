<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\Profile;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class PushNotificationService
{
    public function __construct(
        private readonly Messaging $messaging
    ) {}

    /**
     * Send a push notification to a profile via FCM.
     *
     * @param  array<string, string>  $data  Extra data payload for Flutter navigation.
     */
    public function send(
        Profile $recipient,
        string $title,
        string $body,
        NotificationType $type,
        ?string $targetId = null,
    ): bool {
        if (empty($recipient->device_token)) {
            return false;
        }

        try {
            $data = array_filter([
                'type' => $type->value,
                'id' => $targetId ?? '',
            ]);

            $message = CloudMessage::withTarget('token', $recipient->device_token)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);

            return true;
        } catch (MessagingException $e) {
            // Token expired / unregistered — clear it so we don't keep trying
            if ($this->isTokenInvalid($e)) {
                $recipient->update([
                    'device_token' => null,
                    'device_platform' => null,
                ]);

                Log::info('FCM: cleared invalid device token', [
                    'profile_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            } else {
                Log::warning('FCM: failed to send push notification', [
                    'profile_id' => $recipient->id,
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error('FCM: unexpected error', [
                'profile_id' => $recipient->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check whether the FCM exception indicates an invalid/expired token.
     */
    private function isTokenInvalid(MessagingException $e): bool
    {
        $invalidMessages = [
            'UNREGISTERED',
            'INVALID_ARGUMENT',
            'registration-token-not-registered',
        ];

        foreach ($invalidMessages as $message) {
            if (str_contains($e->getMessage(), $message)) {
                return true;
            }
        }

        return false;
    }
}
