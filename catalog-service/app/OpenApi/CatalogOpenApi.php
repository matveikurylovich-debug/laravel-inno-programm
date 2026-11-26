<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', description: 'Каталог магазинов, категорий, товаров и остатков', title: 'Catalog Service API')]
#[OA\Server(url: '/', description: 'Catalog Service')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', description: 'JWT access token', bearerFormat: 'JWT', scheme: 'bearer')]
final class CatalogOpenApi {}
