<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\SubscriptionPlan;
use App\Support\AppleSubscriptionProducts;
use Tests\TestCase;

/**
 * Guards the Apple IAP product-id allow-list. These ids must match the real
 * App Store Connect product identifiers byte-for-byte — the verify/restore
 * requests reject anything not in this list before contacting Apple, so a wrong
 * default silently breaks end-to-end purchase verification.
 */
class AppleSubscriptionProductsTest extends TestCase
{
    public function test_allow_list_contains_both_production_product_ids_by_default(): void
    {
        $products = AppleSubscriptionProducts::all();

        $this->assertContains('com.kolabing.kolabingApp.subscription.monthly', $products);
        $this->assertContains('com.kolabing.kolabingApp.subscription.three_months', $products);
        $this->assertContains('com.kolabing.kolabingApp.subscription.pro_monthly', $products);
    }

    public function test_only_the_pro_product_unlocks_the_pro_plan(): void
    {
        $this->assertSame(SubscriptionPlan::Pro, AppleSubscriptionProducts::planFor('com.kolabing.kolabingApp.subscription.pro_monthly'));
        $this->assertSame(SubscriptionPlan::Standard, AppleSubscriptionProducts::planFor('com.kolabing.kolabingApp.subscription.monthly'));
        $this->assertSame(SubscriptionPlan::Standard, AppleSubscriptionProducts::planFor('com.kolabing.kolabingApp.subscription.three_months'));
    }

    public function test_an_unknown_or_missing_product_is_never_pro(): void
    {
        foreach (['com.evil.pro', '', null, 123, ['x']] as $id) {
            $this->assertSame(SubscriptionPlan::Standard, AppleSubscriptionProducts::planFor($id));
        }

        // Even with the Pro product id blanked in config, nothing becomes Pro.
        config(['subscriptions.business.apple.pro_monthly.apple_product_id' => '']);
        $this->assertSame(SubscriptionPlan::Standard, AppleSubscriptionProducts::planFor(''));
        $this->assertSame(SubscriptionPlan::Standard, AppleSubscriptionProducts::planFor(null));
    }

    public function test_product_ids_are_read_from_env_overrides(): void
    {
        config([
            'subscriptions.business.apple.monthly.apple_product_id' => 'com.example.custom.monthly',
            'subscriptions.business.apple.three_months.apple_product_id' => 'com.example.custom.quarterly',
            'subscriptions.business.apple.pro_monthly.apple_product_id' => 'com.example.custom.pro',
        ]);

        $products = AppleSubscriptionProducts::all();

        $this->assertSame(
            ['com.example.custom.monthly', 'com.example.custom.quarterly', 'com.example.custom.pro'],
            $products,
        );
    }
}
