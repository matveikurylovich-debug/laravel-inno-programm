<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/stores/{storeId}/categories/tree',
    summary: 'Дерево категорий магазина',
    tags: ['Categories'],
    parameters: [
        new OA\Parameter(name: 'storeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Дерево категорий'),
    ]
)]
#[OA\Post(
    path: '/api/v1/categories',
    summary: 'Создать категорию',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['store_id', 'name'],
            properties: [
                new OA\Property(property: 'store_id', type: 'integer'),
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'slug', type: 'string', nullable: true),
                new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                new OA\Property(property: 'position', type: 'integer', nullable: true),
            ]
        )
    ),
    tags: ['Categories'],
    responses: [
        new OA\Response(response: 201, description: 'Категория создана'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Put(
    path: '/api/v1/categories/{category}',
    summary: 'Обновить категорию',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'slug', type: 'string', nullable: true),
                new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                new OA\Property(property: 'position', type: 'integer'),
            ]
        )
    ),
    tags: ['Categories'],
    parameters: [
        new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Категория обновлена'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Patch(
    path: '/api/v1/categories/{category}',
    summary: 'Частично обновить категорию',
    security: [['bearerAuth' => []]],
    tags: ['Categories'],
    parameters: [
        new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Категория обновлена'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Delete(
    path: '/api/v1/categories/{category}',
    summary: 'Удалить категорию',
    security: [['bearerAuth' => []]],
    tags: ['Categories'],
    parameters: [
        new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Категория удалена'),
    ]
)]
final class CategoriesOpenApi {}
