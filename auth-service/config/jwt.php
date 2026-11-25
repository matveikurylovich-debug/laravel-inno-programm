<?php

return [
    'cookie_secure' => env('SESSION_SECURE_COOKIE', true),
    'refresh_grace_seconds' => (int) env('JWT_REFRESH_GRACE_SECONDS', 30),
    'cookies' => [
        'access' => env('JWT_ACCESS_COOKIE', '__Host-access'),
        'refresh' => env('JWT_REFRESH_COOKIE', 'refresh_token'),
    ],
    'keys' => [
        'refresh' => env('JWT_REFRESH_SECRET'),
    ],
    'private_key_path' => env('JWT_ACCESS_PRIVATE_KEY_PATH'),
    'public_key_path' => env('JWT_ACCESS_PUBLIC_KEY_PATH'),
    'previous_public_key_path' => env('JWT_ACCESS_PREVIOUS_PUBLIC_KEY_PATH'),
    'kid' => env('JWT_ACCESS_KID', 'auth-1'),
    'previous_kid' => env('JWT_ACCESS_PREVIOUS_KID'),
    'issuer' => env('JWT_ISSUER', 'http://auth-app'),
    'audience' => env('JWT_AUDIENCE', 'ecommerce'),
    'ttl' => [
        'access' => (int) env('JWT_ACCESS_TTL', 900),
        'refresh' => (int) env('JWT_REFRESH_TTL', 1209600),
    ],
    'algo' => 'RS256',
];
