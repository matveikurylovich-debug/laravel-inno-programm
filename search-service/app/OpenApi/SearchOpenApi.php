<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    description: 'Полнотекстовый поиск товаров и фасетная фильтрация в Elasticsearch',
    title: 'Search Service API',
)]
#[OA\Server(url: '/', description: 'Search Service')]
#[OA\Get(
    path: '/api/search',
    summary: 'Поиск товаров',
    tags: ['Search'],
    parameters: [
        new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string', maxLength: 200)),
        new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'price_min', in: 'query', required: false, schema: new OA\Schema(type: 'number', minimum: 0)),
        new OA\Parameter(name: 'price_max', in: 'query', required: false, schema: new OA\Schema(type: 'number', minimum: 0)),
        new OA\Parameter(name: 'in_stock', in: 'query', required: false, schema: new OA\Schema(type: 'boolean')),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', maximum: 100, minimum: 1)),
        new OA\Parameter(name: 'sort', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['relevance', 'price_asc', 'price_desc'])),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Результаты поиска и фасеты'),
        new OA\Response(response: 422, description: 'Ошибка валидации параметров (application/problem+json)'),
        new OA\Response(response: 503, description: 'Сервис поиска временно недоступен (application/problem+json)'),
    ],
)]
final class SearchOpenApi {}
