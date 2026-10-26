<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Order Service API',
    description: 'Заказы, оплата и Stripe webhooks'
)]
#[OA\Server(url: '/', description: 'Order Service')]
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
