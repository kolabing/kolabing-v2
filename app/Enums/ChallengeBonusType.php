<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeBonusType: string
{
    case DiscountPercent = 'discount_percent';
    case FreeItem = 'free_item';
    case FreeService = 'free_service';
    case Custom = 'custom';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::DiscountPercent => 'Discount (%)',
            self::FreeItem => 'Free item',
            self::FreeService => 'Free service',
            self::Custom => 'Custom',
        };
    }
}
