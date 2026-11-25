<?php

return [
    'access_secret' => env('JWT_ACCESS_SECRET'),
    'algorithm' => env('JWT_ALGO', 'HS256'),
    'resolve_eloquent_user' => false,
];
