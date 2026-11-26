<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Post(path: '/webhooks/stripe', summary: 'Stripe webhook', requestBody: new OA\RequestBody(
    required: true,
    content: new OA\JsonContent(
        description: 'Сырое тело события Stripe',
        type: 'object',
    ),
), tags: ['Webhooks'], parameters: [
    new OA\Parameter(
        name: 'Stripe-Signature',
        in: 'header',
        required: true,
        schema: new OA\Schema(type: 'string'),
    ),
], responses: [
    new OA\Response(response: 200, description: 'Событие принято'),
    new OA\Response(response: 400, description: 'Невалидный payload или сигнатура'),
])]
final class WebhooksEndpoints {}
