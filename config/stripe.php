<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => env('STRIPE_CURRENCY', 'eur'),
    'connect_country' => env('STRIPE_CONNECT_COUNTRY', 'DE'),
    'connect_return_url' => env('STRIPE_CONNECT_RETURN_URL'),
    'connect_refresh_url' => env('STRIPE_CONNECT_REFRESH_URL'),
];
