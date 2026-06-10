<?php

return [
    'business' => [
        'apple' => [
            'monthly' => [
                'price' => 39.99,
                'apple_product_id' => env('APPLE_IAP_MONTHLY_PRODUCT_ID', 'com.kolabing.app.subscription.monthly'),
            ],
            'three_months' => [
                'price' => env('APPLE_IAP_THREE_MONTHS_PRICE'),
                'apple_product_id' => env('APPLE_IAP_THREE_MONTHS_PRODUCT_ID', 'com.kolabing.kolabingApp.subscription.three_months'),
            ],
        ],
    ],
];
