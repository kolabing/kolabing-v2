<?php

return [
    'business' => [
        'apple' => [
            'monthly' => [
                'price' => 49,
                'apple_product_id' => env('APPLE_IAP_MONTHLY_PRODUCT_ID', 'com.kolabing.kolabingApp.subscription.monthly'),
            ],
            'three_months' => [
                'price' => env('APPLE_IAP_THREE_MONTHS_PRICE'),
                'apple_product_id' => env('APPLE_IAP_THREE_MONTHS_PRODUCT_ID', 'com.kolabing.kolabingApp.subscription.three_months'),
            ],
        ],
    ],
];
