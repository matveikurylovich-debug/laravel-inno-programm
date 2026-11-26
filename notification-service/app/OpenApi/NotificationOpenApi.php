<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(version: '1.0.0', description: 'Внутренний API журнала уведомлений и повторной отправки для ролей admin и analyst.', title: 'Notification Service API')]
#[OA\Server(url: '/', description: 'Notification Service')]
#[OA\SecurityScheme(securityScheme: 'bearerAuth', type: 'http', bearerFormat: 'JWT', scheme: 'bearer')]
#[OA\Schema(
    schema: 'NotificationLog',
    required: ['id', 'event', 'channel', 'status', 'attempts', 'is_dlq'],
    properties: [
        new OA\Property(property: 'id', type: 'string', example: '65f000000000000000000001'),
        new OA\Property(property: 'event_id', type: 'string', example: '11111111-1111-4111-8111-111111111111', nullable: true),
        new OA\Property(property: 'user_id', type: 'integer', example: 4, nullable: true),
        new OA\Property(property: 'event', type: 'string', example: 'order.paid'),
        new OA\Property(property: 'channel', type: 'string', example: 'email', enum: ['email']),
        new OA\Property(property: 'recipient', type: 'string', example: 'buyer@example.com', nullable: true),
        new OA\Property(property: 'status', type: 'string', example: 'sent', enum: ['pending', 'sent', 'failed']),
        new OA\Property(property: 'attempts', type: 'integer', example: 1),
        new OA\Property(property: 'is_dlq', type: 'boolean', example: false),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        new OA\Property(property: 'sent_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'replayed_by', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'replayed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ],
)]
#[OA\Schema(
    schema: 'ProblemDetails',
    required: ['type', 'title', 'status', 'detail'],
    properties: [
        new OA\Property(property: 'type', type: 'string', example: 'about:blank'),
        new OA\Property(property: 'title', type: 'string', example: 'Unprocessable Content'),
        new OA\Property(property: 'status', type: 'integer', example: 422),
        new OA\Property(property: 'detail', type: 'string', example: 'Секретные данные этого уведомления не сохраняются и не подлежат повторной отправке.'),
        new OA\Property(property: 'code', type: 'string', example: 'notification_secret_redacted', nullable: true),
    ],
)]
final class NotificationOpenApi {}
