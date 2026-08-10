<?php

return [
    'currency' => env('STRIPE_CURRENCY', 'eur'),
    'tiers' => [
        'golden_ad' => [
            'tier' => 'gold',
            'price' => 249.00,
        ],
        'silver_ad' => [
            'tier' => 'silver',
            'price' => 129.00,
        ],
        'normal_ad' => [
            'tier' => 'normal',
            'price' => 69.00,
        ],
    ],
    'checkout_success_url' => env('STRIPE_AD_CHECKOUT_SUCCESS_URL'),
    'checkout_cancel_url' => env('STRIPE_AD_CHECKOUT_CANCEL_URL'),
];
