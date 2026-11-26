<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/stores',
    summary: 'Список магазинов',
    tags: ['Stores'],
    responses: [
        new OA\Response(response: 200, description: 'Список магазинов'),
    ]
)]
#[OA\Post(
    path: '/api/v1/stores',
    summary: 'Создать магазин',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'slug', type: 'string', nullable: true),
                new OA\Property(property: 'city', type: 'string', nullable: true),
                new OA\Property(property: 'address', type: 'string', nullable: true),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'working_hours', type: 'string', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
            ]
        )
    ),
    tags: ['Stores'],
    responses: [
        new OA\Response(response: 201, description: 'Магазин создан'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 403, description: 'Нет доступа'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Put(
    path: '/api/v1/stores/{store}',
    summary: 'Обновить магазин',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'slug', type: 'string', nullable: true),
                new OA\Property(property: 'city', type: 'string'),
                new OA\Property(property: 'address', type: 'string'),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
                new OA\Property(property: 'working_hours', type: 'string'),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ]
        )
    ),
    tags: ['Stores'],
    parameters: [
        new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Магазин обновлён'),
        new OA\Response(response: 403, description: 'Нет доступа'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Patch(
    path: '/api/v1/stores/{store}',
    summary: 'Частично обновить магазин',
    security: [['bearerAuth' => []]],
    tags: ['Stores'],
    parameters: [
        new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Магазин обновлён'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Delete(
    path: '/api/v1/stores/{store}',
    summary: 'Удалить магазин',
    security: [['bearerAuth' => []]],
    tags: ['Stores'],
    parameters: [
        new OA\Parameter(name: 'store', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Магазин отключён'),
        new OA\Response(response: 403, description: 'Нет доступа'),
        new OA\Response(response: 409, description: 'Нельзя отключить магазин с активными резервами'),
    ]
)]
final class StoresOpenApi {}
