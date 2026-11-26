<?php

return [
    'jwks_path' => env('JWT_JWKS_PATH'),
    'jwks_url' => env('JWT_JWKS_URL'),
    'jwks_cache' => env('JWT_JWKS_CACHE'),
    'issuer' => env('JWT_ISSUER', 'http://auth-app'),
    'audience' => env('JWT_AUDIENCE', 'ecommerce'),
    'resolve_eloquent_user' => false,
];
