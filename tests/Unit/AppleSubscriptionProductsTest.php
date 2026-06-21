<?php

declare(strict_types=1);

namespace Tests\Unit;

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
    }

    public function test_product_ids_are_read_from_env_overrides(): void
    {
        config([
            'subscriptions.business.apple.monthly.apple_product_id' => 'com.example.custom.monthly',
            'subscriptions.business.apple.three_months.apple_product_id' => 'com.example.custom.quarterly',
        ]);

        $products = AppleSubscriptionProducts::all();

        $this->assertSame(
            ['com.example.custom.monthly', 'com.example.custom.quarterly'],
            $products,
        );
    }
}
