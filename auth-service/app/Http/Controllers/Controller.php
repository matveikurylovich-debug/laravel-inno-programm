<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Auth Service API',
    description: 'Аутентификация, профиль, админка пользователей и API Gateway'
)]
#[OA\Server(url: '/', description: 'Auth Service / Gateway')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT access token'
)]
abstract class Controller
{
    //
}
