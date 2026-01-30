<?php

declare(strict_types=1);

namespace App\Enums;

enum NotificationType: string
{
    case NewMessage = 'new_message';
    case ApplicationReceived = 'application_received';
    case ApplicationAccepted = 'application_accepted';
    case ApplicationDeclined = 'application_declined';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
