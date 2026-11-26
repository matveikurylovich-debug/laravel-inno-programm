<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/register',
    operationId: 'authRegister',
    summary: 'Регистрация',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name', 'email', 'phone', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'name', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'phone', type: 'string'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ],
        ),
    ),
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 201, description: 'Пользователь создан, код 2FA отправлен на почту'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
        new OA\Response(response: 429, description: 'Превышен лимит запросов'),
    ],
)]
#[OA\Post(
    path: '/api/v1/login',
    operationId: 'authLogin',
    summary: 'Логин (запуск 2FA)',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
            ],
        ),
    ),
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 200, description: 'Код 2FA отправлен на почту'),
        new OA\Response(response: 401, description: 'Неверный логин или пароль'),
        new OA\Response(response: 429, description: 'Слишком много попыток входа'),
    ],
)]
#[OA\Post(
    path: '/api/v1/two-factor/verify',
    operationId: 'authVerifyTwoFactor',
    summary: 'Подтверждение 2FA',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['challenge_id', 'code'],
            properties: [
                new OA\Property(property: 'challenge_id', type: 'string', maxLength: 40, minLength: 40),
                new OA\Property(property: 'code', type: 'string', maxLength: 6, minLength: 6),
            ],
        ),
    ),
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 200, description: 'Токены выданы'),
        new OA\Response(response: 422, description: 'Неверный код'),
        new OA\Response(response: 429, description: 'Слишком много попыток'),
    ],
)]
#[OA\Post(
    path: '/api/v1/two-factor/resend',
    operationId: 'authResendTwoFactor',
    summary: 'Повторная отправка кода 2FA',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['challenge_id'],
            properties: [
                new OA\Property(property: 'challenge_id', type: 'string', maxLength: 40, minLength: 40),
            ],
        ),
    ),
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 200, description: 'Новый код отправлен'),
        new OA\Response(response: 422, description: 'Нет активной проверки пароля'),
        new OA\Response(response: 429, description: 'Слишком много повторных отправок'),
    ],
)]
#[OA\Post(
    path: '/api/v1/forgot-password',
    operationId: 'authForgotPassword',
    summary: 'Запрос сброса пароля',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email'),
            ],
        ),
    ),
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 200, description: 'Ссылка отправлена (если аккаунт существует)'),
        new OA\Response(response: 429, description: 'Превышен лимит запросов'),
    ],
)]
#[OA\Post(
    path: '/api/v1/reset-password',
    operationId: 'authResetPassword',
    summary: 'Сброс пароля',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token', 'email', 'password', 'password_confirmation'],
            properties: [
                new OA\Property(property: 'token', type: 'string'),
                new OA\Property(property: 'email', type: 'string', format: 'email'),
                new OA\Property(property: 'password', type: 'string', format: 'password'),
                new OA\Property(property: 'password_confirmation', type: 'string', format: 'password'),
            ],
        ),
    ),
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 200, description: 'Пароль обновлён'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
        new OA\Response(response: 429, description: 'Превышен лимит запросов'),
    ],
)]
#[OA\Post(
    path: '/api/v1/refresh',
    operationId: 'authRefresh',
    summary: 'Обновление access-токена',
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['refresh_token'],
            properties: [
                new OA\Property(property: 'refresh_token', type: 'string'),
            ],
        ),
    ),
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 200, description: 'Новая пара токенов'),
        new OA\Response(response: 401, description: 'Недействительный refresh-токен'),
    ],
)]
#[OA\Post(
    path: '/api/v1/logout',
    operationId: 'authLogout',
    summary: 'Выход из текущей сессии',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'refresh_token', type: 'string', nullable: true),
            ],
        ),
    ),
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 200, description: 'Успешный выход'),
    ],
)]
#[OA\Post(
    path: '/api/v1/logout-all',
    operationId: 'authLogoutAll',
    summary: 'Завершение всех сессий пользователя',
    security: [['bearerAuth' => []]],
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 200, description: 'Все сессии завершены'),
        new OA\Response(response: 401, description: 'Не авторизован'),
    ],
)]
#[OA\Get(
    path: '/api/v1/me',
    operationId: 'authMe',
    summary: 'Текущий пользователь',
    security: [['bearerAuth' => []]],
    tags: ['Auth'],
    responses: [
        new OA\Response(response: 200, description: 'Профиль пользователя'),
        new OA\Response(response: 401, description: 'Не авторизован'),
    ],
)]
final class AuthEndpoints {}
