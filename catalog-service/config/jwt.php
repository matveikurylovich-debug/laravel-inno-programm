<?php

return [
    'secret' => env('JWT_ACCESS_SECRET'),
    'algo'   => env('JWT_ALGO', 'HS256'),
];