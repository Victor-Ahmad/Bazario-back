<?php

return [
    'currency' => env('STRIPE_CURRENCY', 'eur'),
    'announcement' => [
        'price_per_day' => 20.00,
        'duration_days' => 1,
    ],
    'checkout_success_url' => env('STRIPE_LISTING_CHECKOUT_SUCCESS_URL'),
    'checkout_cancel_url' => env('STRIPE_LISTING_CHECKOUT_CANCEL_URL'),
];
