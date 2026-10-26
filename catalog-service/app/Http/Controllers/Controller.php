<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Catalog Service API',
    description: 'Каталог магазинов, категорий, товаров и остатков'
)]
#[OA\Server(url: '/', description: 'Catalog Service')]
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
