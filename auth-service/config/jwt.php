<?php

return [
    'cookie_secure' => env('SESSION_SECURE_COOKIE', true),
    'refresh_grace_seconds' => (int) env('JWT_REFRESH_GRACE_SECONDS', 30),
    'cookies' => [
        'access' => env('JWT_ACCESS_COOKIE', '__Host-access'),
        'refresh' => env('JWT_REFRESH_COOKIE', 'refresh_token'),
    ],
    'keys' => [
        'access' => env('JWT_ACCESS_SECRET'),
        'refresh' => env('JWT_REFRESH_SECRET'),
    ],
    'ttl' => [
        'access' => (int) env('JWT_ACCESS_TTL', 900),
        'refresh' => (int) env('JWT_REFRESH_TTL', 1209600),
    ],
    'algo' => 'HS256',
];
