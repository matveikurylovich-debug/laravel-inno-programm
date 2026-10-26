<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Recommendation Service API',
    description: 'AI-рекомендации и чат'
)]
#[OA\Server(url: '/', description: 'Recommendation Service')]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'JWT access token'
)]
final class OpenApiSpec
{
}
