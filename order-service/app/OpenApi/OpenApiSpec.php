<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', description: 'Заказы, оплата и Stripe webhooks', title: 'Order Service API')]
#[OA\Server(url: '/', description: 'Order Service')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', description: 'JWT access token', bearerFormat: 'JWT', scheme: 'bearer')]
final class OpenApiSpec {}
