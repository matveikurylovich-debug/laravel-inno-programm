<?php

declare(strict_types=1);

return [
    'cookie_secure' => (bool) env('SESSION_SECURE_COOKIE', true),
    'refresh_grace_seconds' => (int) env('JWT_REFRESH_GRACE_SECONDS', 30),
    'cookies' => [
        'access' => (string) env('JWT_ACCESS_COOKIE', '__Host-access'),
        'refresh' => (string) env('JWT_REFRESH_COOKIE', 'refresh_token'),
    ],
    'keys' => [
        'refresh' => (string) env('JWT_REFRESH_SECRET', ''),
    ],
    'private_key_path' => (string) env('JWT_ACCESS_PRIVATE_KEY_PATH', ''),
    'public_key_path' => (string) env('JWT_ACCESS_PUBLIC_KEY_PATH', ''),
    'previous_public_key_path' => (string) env('JWT_ACCESS_PREVIOUS_PUBLIC_KEY_PATH', ''),
    'kid' => (string) env('JWT_ACCESS_KID', 'auth-1'),
    'previous_kid' => (string) env('JWT_ACCESS_PREVIOUS_KID', ''),
    'issuer' => (string) env('JWT_ISSUER', 'http://auth-app'),
    'audience' => (string) env('JWT_AUDIENCE', 'ecommerce'),
    'ttl' => [
        'access' => (int) env('JWT_ACCESS_TTL', 900),
        'refresh' => (int) env('JWT_REFRESH_TTL', 1209600),
    ],
    'algo' => 'RS256',
];
