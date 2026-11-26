<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/orders',
    summary: 'Список заказов',
    security: [['bearerAuth' => []]],
    tags: ['Orders'],
    responses: [
        new OA\Response(response: 200, description: 'Пагинированный список заказов'),
        new OA\Response(response: 401, description: 'Не авторизован'),
    ],
)]
#[OA\Post(path: '/api/orders', summary: 'Создать заказ', security: [['bearerAuth' => []]], requestBody: new OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        required: ['email', 'store_id', 'location_id', 'items'],
        properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email'),
            new OA\Property(property: 'store_id', type: 'integer'),
            new OA\Property(property: 'location_id', type: 'integer'),
            new OA\Property(
                property: 'items',
                type: 'array',
                items: new OA\Items(
                    required: ['product_id', 'quantity'],
                    properties: [
                        new OA\Property(property: 'product_id', type: 'integer'),
                        new OA\Property(property: 'quantity', type: 'integer', minimum: 1),
                    ],
                    type: 'object',
                ),
            ),
        ],
    ),
), tags: ['Orders'], parameters: [
    new OA\Parameter(
        name: 'Idempotency-Key',
        description: 'Ключ идемпотентности',
        in: 'header',
        required: true,
        schema: new OA\Schema(type: 'string'),
    ),
], responses: [
    new OA\Response(response: 201, description: 'Заказ создан'),
    new OA\Response(response: 422, description: 'Ошибка валидации / бизнес-логики'),
    new OA\Response(response: 401, description: 'Не авторизован'),
])]
#[OA\Get(
    path: '/api/orders/{order}',
    summary: 'Детали заказа',
    security: [['bearerAuth' => []]],
    tags: ['Orders'],
    parameters: [
        new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Заказ'),
        new OA\Response(response: 403, description: 'Недостаточно прав'),
        new OA\Response(response: 404, description: 'Не найден'),
    ],
)]
#[OA\Post(
    path: '/api/orders/{order}/cancel',
    summary: 'Отменить заказ',
    security: [['bearerAuth' => []]],
    tags: ['Orders'],
    parameters: [
        new OA\Parameter(name: 'order', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Заказ отменён'),
        new OA\Response(response: 409, description: 'Отмена недоступна'),
        new OA\Response(response: 403, description: 'Недостаточно прав'),
        new OA\Response(response: 404, description: 'Не найден'),
    ],
)]
final class OrdersEndpoints {}
