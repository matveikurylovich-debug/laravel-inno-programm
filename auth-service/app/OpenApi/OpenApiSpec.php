<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Аутентификация, 2FA, управление профилем, RBAC и проверка JWT',
    title: 'Auth Service API',
)]
#[OA\Server(url: '/', description: 'Auth Service / API Gateway')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    description: 'JWT access token',
    bearerFormat: 'JWT',
    scheme: 'bearer',
)]
final class OpenApiSpec {}
