<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Patch(
    path: '/api/v1/profile',
    operationId: 'profileUpdate',
    summary: 'Обновить профиль',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'phone', type: 'string', nullable: true),
            ],
        ),
    ),
    tags: ['Profile'],
    responses: [
        new OA\Response(response: 200, description: 'Профиль обновлён'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ],
)]
#[OA\Post(
    path: '/api/v1/profile/email',
    operationId: 'profileRequestEmailChange',
    summary: 'Запросить смену email',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'current_password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'current_password', type: 'string', format: 'password'),
            ],
        ),
    ),
    tags: ['Profile'],
    responses: [
        new OA\Response(response: 200, description: 'Отправлены ссылки подтверждения и отмены'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ],
)]
#[OA\Put(
    path: '/api/v1/profile/password',
    operationId: 'profileUpdatePassword',
    summary: 'Сменить пароль',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['current_password', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'current_password', type: 'string', format: 'password'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ],
        ),
    ),
    tags: ['Profile'],
    responses: [
        new OA\Response(response: 200, description: 'Пароль обновлён'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ],
)]
#[OA\Delete(
    path: '/api/v1/profile',
    operationId: 'profileDestroy',
    summary: 'Удалить аккаунт',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['password'],
            properties: [
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ],
        ),
    ),
    tags: ['Profile'],
    responses: [
        new OA\Response(response: 200, description: 'Аккаунт удалён'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 422, description: 'Неверный пароль'),
    ],
)]
final class ProfileEndpoints {}
