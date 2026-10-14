<?php

declare(strict_types=1);

return [
    'secret' => env('STRIPE_SECRET_KEY'),
    'publishable' => env('STRIPE_KEY', env('STRIPE_PUBLISHABLE_KEY')),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'currency' => env('STRIPE_CURRENCY', 'usd'),
];
