<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/stores/{storeId}/products',
    description: 'Каталог товаров магазина с фильтрацией по категории и пагинацией',
    summary: 'Список товаров магазина',
    tags: ['Products'],
    parameters: [
        new OA\Parameter(name: 'storeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1, minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100, minimum: 1)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Список товаров'),
        new OA\Response(response: 422, description: 'Ошибка валидации параметров пагинации или фильтра'),
    ]
)]
#[OA\Get(
    path: '/api/v1/products/{id}',
    summary: 'Карточка товара',
    tags: ['Products'],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Товар с наличием по точкам'),
        new OA\Response(response: 404, description: 'Товар не найден'),
    ]
)]
#[OA\Post(
    path: '/api/v1/products',
    summary: 'Создать товар',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['store_id', 'category_id', 'name', 'price'],
            properties: [
                new OA\Property(property: 'store_id', type: 'integer'),
                new OA\Property(property: 'category_id', type: 'integer'),
                new OA\Property(property: 'sku', type: 'string', nullable: true),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'slug', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'price', type: 'number'),
                new OA\Property(property: 'attributes', type: 'object', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
                new OA\Property(property: 'quantity', type: 'integer', nullable: true),
            ]
        )
    ),
    tags: ['Products'],
    responses: [
        new OA\Response(response: 201, description: 'Товар создан'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 403, description: 'Нет прав на запись в каталог'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Put(
    path: '/api/v1/products/{product}',
    summary: 'Обновить товар',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'category_id', type: 'integer', nullable: true),
                new OA\Property(property: 'sku', type: 'string', nullable: true),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'slug', type: 'string', nullable: true),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'price', type: 'number'),
                new OA\Property(property: 'attributes', type: 'object', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ]
        )
    ),
    tags: ['Products'],
    parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Товар обновлён'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Patch(
    path: '/api/v1/products/{product}',
    summary: 'Частично обновить товар',
    security: [['bearerAuth' => []]],
    tags: ['Products'],
    parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Товар обновлён'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Delete(
    path: '/api/v1/products/{product}',
    summary: 'Удалить товар',
    security: [['bearerAuth' => []]],
    tags: ['Products'],
    parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Товар удалён'),
        new OA\Response(response: 401, description: 'Не авторизован'),
    ]
)]
#[OA\Put(
    path: '/api/v1/products/{product}/stock',
    summary: 'Обновить остаток товара',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['quantity'],
            properties: [
                new OA\Property(property: 'quantity', type: 'integer', minimum: 0),
                new OA\Property(property: 'location_id', type: 'integer', nullable: true),
            ]
        )
    ),
    tags: ['Products'],
    parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Остаток обновлён'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Patch(
    path: '/api/v1/products/{product}/stock',
    summary: 'Частично обновить остаток товара',
    security: [['bearerAuth' => []]],
    tags: ['Products'],
    parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Остаток обновлён'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
final class ProductsOpenApi {}
