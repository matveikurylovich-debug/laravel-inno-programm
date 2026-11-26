<?php

declare(strict_types=1);

return [
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'api'),
        'passwords' => null,
    ],

    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => null,
        ],
    ],

    'providers' => [],

    'passwords' => [],

    'password_timeout' => (int) env('AUTH_PASSWORD_TIMEOUT', 10800),
];
