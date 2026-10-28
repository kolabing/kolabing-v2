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
            // Venue Pro for hotels (SERR-201): the EUR 299 plan as an App Store
            // subscription, in the same group as the two above (highest level), so
            // moving between them is an Apple upgrade/downgrade on one subscription.
            // `plan` is what the product unlocks; unknown products are standard.
            'pro_monthly' => [
                'price' => env('APPLE_IAP_PRO_MONTHLY_PRICE', 299),
                'plan' => 'pro',
                'apple_product_id' => env('APPLE_IAP_PRO_MONTHLY_PRODUCT_ID', 'com.kolabing.kolabingApp.subscription.pro_monthly'),
            ],
        ],

        // Web checkout (Stripe). `stripe_price_id` is the Stripe Price the
        // Checkout Session bills; the EUR `price` is display-only (kept in sync
        // with the Stripe Price for reference). Launch pricing: EUR49/mo, EUR129/3mo.
        // `plan` is what the entry unlocks (App\Enums\SubscriptionPlan). Venue Pro
        // (BE-NF-68) is also an App Store product (`apple.pro_monthly`, SERR-201).
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
];
