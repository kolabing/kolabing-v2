<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\SubscriptionPlan;

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
            config('subscriptions.business.apple.pro_monthly.apple_product_id'),
        ], static fn ($value): bool => is_string($value) && $value !== ''));
    }

    /**
     * The plan an App Store product unlocks. Only the Pro product is Pro; anything
     * else, including an unknown or missing id, is the standard plan, so a mistake
     * here can never hand out Venue Pro.
     */
    public static function planFor(mixed $productId): SubscriptionPlan
    {
        $pro = config('subscriptions.business.apple.pro_monthly.apple_product_id');

        return is_string($productId) && is_string($pro) && $pro !== '' && $productId === $pro
            ? SubscriptionPlan::Pro
            : SubscriptionPlan::Standard;
    }
}
