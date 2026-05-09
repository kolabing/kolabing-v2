<?php

declare(strict_types=1);

namespace App\Services\Notifications;

use App\Models\DeviceToken;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FcmClient
{
    public function __construct(
        private readonly Messaging $messaging
    ) {}

    /**
     * @param  array<string, string>  $data
     */
    public function send(DeviceToken $deviceToken, string $title, string $body, array $data): string
    {
        $message = CloudMessage::withTarget('token', $deviceToken->token)
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        return $this->messaging->send($message);
    }

    public function isInvalidToken(\Throwable $exception): bool
    {
        $invalidMessages = [
            'UNREGISTERED',
            'INVALID_ARGUMENT',
            'registration-token-not-registered',
        ];

        foreach ($invalidMessages as $message) {
            if (str_contains($exception->getMessage(), $message)) {
                return true;
            }
        }

        return false;
    }
}
