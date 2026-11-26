<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Get(
    path: '/api/v1/notifications',
    operationId: 'listNotifications',
    description: 'Журнал уведомлений. Доступен ролям admin и analyst.',
    summary: 'Список уведомлений',
    security: [['bearerAuth' => []]],
    tags: ['Notifications'],
    parameters: [
        new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1)),
        new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', maximum: 100, minimum: 1)),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Страница журнала'),
        new OA\Response(response: 401, description: 'Требуется авторизация'),
        new OA\Response(response: 403, description: 'Недостаточно прав'),
    ],
)]
#[OA\Get(
    path: '/api/v1/notifications/{id}',
    operationId: 'showNotification',
    summary: 'Карточка уведомления',
    security: [['bearerAuth' => []]],
    tags: ['Notifications'],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Уведомление без секретных полей'),
        new OA\Response(response: 401, description: 'Требуется авторизация'),
        new OA\Response(response: 404, description: 'Уведомление не найдено'),
    ],
)]
#[OA\Post(
    path: '/api/v1/notifications/{id}/replay',
    operationId: 'replayNotification',
    summary: 'Повторная отправка',
    security: [['bearerAuth' => []]],
    tags: ['Notifications'],
    parameters: [
        new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Отправка поставлена повторно'),
        new OA\Response(response: 401, description: 'Требуется авторизация'),
        new OA\Response(response: 403, description: 'Недостаточно прав'),
        new OA\Response(response: 422, description: 'Повторная отправка запрещена'),
    ],
)]
final class NotificationEndpoints {}
