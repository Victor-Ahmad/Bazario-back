<?php

return [
    'currency' => env('STRIPE_CURRENCY', 'eur'),
    'allowed_durations' => [1, 2, 3, 4, 5, 6, 7],
    'tiers' => [
        'golden_ad' => ['tier' => 'gold', 'price_per_day' => 50.00],
        'silver_ad' => ['tier' => 'silver', 'price_per_day' => 40.00],
        'normal_ad' => ['tier' => 'normal', 'price_per_day' => 30.00],
    ],
    'checkout_success_url' => env('STRIPE_AD_CHECKOUT_SUCCESS_URL'),
    'checkout_cancel_url' => env('STRIPE_AD_CHECKOUT_CANCEL_URL'),
];
