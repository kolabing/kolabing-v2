<?php

return [
    'business' => [
        'apple' => [
            'monthly' => [
                'price' => 49,
                'apple_product_id' => env('APPLE_IAP_MONTHLY_PRODUCT_ID', 'com.kolabing.kolabingApp.subscription.monthly'),
            ],
            'three_months' => [
                'price' => env('APPLE_IAP_THREE_MONTHS_PRICE', 129),
                'apple_product_id' => env('APPLE_IAP_THREE_MONTHS_PRODUCT_ID', 'com.kolabing.kolabingApp.subscription.three_months'),
            ],
        ],

        // Web checkout (Stripe). `stripe_price_id` is the Stripe Price the
        // Checkout Session bills; the EUR `price` is display-only (kept in sync
        // with the Stripe Price for reference). Launch pricing: EUR49/mo, EUR129/3mo.
        // `plan` is what the entry unlocks (App\Enums\SubscriptionPlan). Venue Pro
        // (BE-NF-68) is sold on the web only, so it has no `apple` counterpart.
        'stripe' => [
            'monthly' => [
                'price' => 49,
                'plan' => 'standard',
                'stripe_price_id' => env('STRIPE_MONTHLY_PRICE_ID'),
            ],
            'three_months' => [
                'price' => 129,
                'plan' => 'standard',
                'stripe_price_id' => env('STRIPE_THREE_MONTHS_PRICE_ID'),
            ],
            // Stripe product "Kolabing Hotel — Monthly" (prod_VJnuMVAiq7be6P); the
            // live Price id is set per environment, like the two plans above.
            'pro_monthly' => [
                'price' => 299,
                'plan' => 'pro',
                'stripe_price_id' => env('STRIPE_HOTEL_MONTHLY_PRICE_ID'),
            ],
        ],
    ],

    // Free-listing expiry (issue #341, BE-NF-73). The free listing ends at the
    // EARLIER of `kolab_limit` completed kolabs or `day_limit` days after the
    // business profile was created — numbers confirmed by Daniel 2026-09-25.
    // `enforcement_city_ids` is empty (off) everywhere by default per the
    // ticket's acceptance criteria; a city is added only once it hits the
    // traction bar (~20 active communities, ~10 closed kolabs/month) and
    // Daniel decides to switch it on. Nothing currently reads this list —
    // App\Services\FreeListingService::isEnforcedForCity() computes the
    // state/expiry but does not yet gate any access; see BACKLOG.md.
    'free_listing' => [
        'kolab_limit' => (int) env('KOLABING_FREE_LISTING_KOLAB_LIMIT', 3),
        'day_limit' => (int) env('KOLABING_FREE_LISTING_DAY_LIMIT', 90),
        'enforcement_city_ids' => array_filter(explode(
            ',',
            (string) env('KOLABING_FREE_LISTING_ENFORCEMENT_CITY_IDS', '')
        )),
    ],
];
