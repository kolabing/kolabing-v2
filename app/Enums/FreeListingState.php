<?php

declare(strict_types=1);

namespace App\Enums;

enum FreeListingState: string
{
    case Free = 'free';
    case Expired = 'expired';
    case Subscribed = 'subscribed';
    case NotApplicable = 'not_applicable';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get the human-readable label for the state.
     */
    public function label(): string
    {
        return match ($this) {
            self::Free => 'Free',
            self::Expired => 'Expired',
            self::Subscribed => 'Subscribed',
            self::NotApplicable => 'Not Applicable',
        };
    }
}
