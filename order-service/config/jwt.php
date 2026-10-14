<?php

declare(strict_types=1);

return [
    'access_secret' => env('JWT_ACCESS_SECRET'),
    'secret' => env('JWT_ACCESS_SECRET'),
    'algo' => env('JWT_ALGO', 'HS256'),
];
