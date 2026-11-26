<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', description: 'AI-рекомендации и консультационный чат', title: 'Recommendation Service API')]
#[OA\Server(url: '/', description: 'Recommendation Service')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', description: 'JWT access token', bearerFormat: 'JWT', scheme: 'bearer')]
#[OA\Get(
    path: '/recommendations/health',
    summary: 'Проверка работоспособности сервиса рекомендаций',
    tags: ['Health'],
    responses: [
        new OA\Response(response: 200, description: 'Сервис доступен'),
    ],
)]
#[OA\Post(
    path: '/recommendations/chat',
    summary: 'Чат рекомендаций',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['message', 'store_id'],
            properties: [
                new OA\Property(property: 'message', type: 'string', maxLength: 400),
                new OA\Property(property: 'store_id', type: 'integer', minimum: 1),
                new OA\Property(property: 'conversation_id', type: 'string', format: 'uuid', nullable: true),
            ],
        ),
    ),
    tags: ['Recommendations'],
    responses: [
        new OA\Response(response: 200, description: 'Ответ ассистента и подобранные товары'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 404, description: 'Диалог не найден'),
        new OA\Response(response: 422, description: 'Ошибка валидации запроса'),
        new OA\Response(response: 429, description: 'Превышен лимит сообщений'),
    ],
)]
#[OA\Get(
    path: '/recommendations/conversations',
    summary: 'Список активных диалогов текущего покупателя',
    security: [['bearerAuth' => []]],
    tags: ['Conversations'],
    responses: [
        new OA\Response(response: 200, description: 'Список диалогов'),
        new OA\Response(response: 401, description: 'Не авторизован'),
    ],
)]
#[OA\Get(
    path: '/recommendations/conversations/{id}',
    summary: 'История сообщений диалога',
    security: [['bearerAuth' => []]],
    tags: ['Conversations'],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid'),
        ),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Сообщения диалога'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 404, description: 'Диалог не найден'),
    ],
)]
#[OA\Delete(
    path: '/recommendations/conversations/{id}',
    summary: 'Удаление диалога',
    security: [['bearerAuth' => []]],
    tags: ['Conversations'],
    parameters: [
        new OA\Parameter(
            name: 'id',
            in: 'path',
            required: true,
            schema: new OA\Schema(type: 'string', format: 'uuid'),
        ),
    ],
    responses: [
        new OA\Response(response: 204, description: 'Диалог удалён'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 404, description: 'Диалог не найден'),
    ],
)]
final class OpenApiSpec
{
}
