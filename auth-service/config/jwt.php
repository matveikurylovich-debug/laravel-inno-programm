<?php

return [
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