<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/admin/users',
    operationId: 'adminUsersIndex',
    description: 'Доступно ролям admin и analyst',
    summary: 'Постраничный список пользователей',
    security: [['bearerAuth' => []]],
    tags: ['Admin'],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Список пользователей и ролей'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 403, description: 'Недостаточно прав'),
    ],
)]
#[OA\Get(
    path: '/api/v1/admin/roles',
    operationId: 'adminRolesIndex',
    description: 'Доступно ролям admin и analyst',
    summary: 'Список доступных ролей',
    security: [['bearerAuth' => []]],
    tags: ['Admin'],
    responses: [
        new OA\Response(response: 200, description: 'Список ролей'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 403, description: 'Недостаточно прав'),
    ],
)]
#[OA\Patch(
    path: '/api/v1/admin/users/{user}/role',
    operationId: 'adminUsersUpdateRole',
    description: 'Доступно только роли admin',
    summary: 'Изменить роль пользователя',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['role'],
            properties: [
                new OA\Property(property: 'role', type: 'string', enum: ['admin', 'analyst', 'customer']),
            ],
        ),
    ),
    tags: ['Admin'],
    parameters: [
        new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Роль обновлена'),
        new OA\Response(response: 403, description: 'Запрещено'),
        new OA\Response(response: 422, description: 'Ошибка валидации или защита последнего администратора'),
    ],
)]
final class AdminEndpoints {}
