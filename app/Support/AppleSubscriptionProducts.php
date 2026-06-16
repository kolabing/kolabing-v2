<?php

declare(strict_types=1);

namespace App\Support;

final class AppleSubscriptionProducts
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values(array_filter([
            config('subscriptions.business.apple.monthly.apple_product_id'),
            config('subscriptions.business.apple.three_months.apple_product_id'),
        ], static fn ($value): bool => is_string($value) && $value !== ''));
    }
}
