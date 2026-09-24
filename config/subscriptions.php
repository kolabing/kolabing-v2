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
            'pro_monthly' => [
                'price' => 299,
                'plan' => 'pro',
                'stripe_price_id' => env('STRIPE_PRO_MONTHLY_PRICE_ID'),
            ],
        ],
    ],
];
