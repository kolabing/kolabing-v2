<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';
    case PastDue = 'past_due';
    case Inactive = 'inactive';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
