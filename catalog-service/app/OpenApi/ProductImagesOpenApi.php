<?php

declare(strict_types=1);

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Post(
    path: '/api/v1/products/{product}/images',
    summary: 'Загрузить изображение товара',
    security: [['bearerAuth' => []]],
    requestBody: new OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['image'],
                properties: [
                    new OA\Property(property: 'image', type: 'string', format: 'binary'),
                    new OA\Property(property: 'is_primary', type: 'boolean', nullable: true),
                ]
            )
        )
    ),
    tags: ['Product Images'],
    parameters: [
        new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 201, description: 'Изображение загружено'),
        new OA\Response(response: 401, description: 'Не авторизован'),
        new OA\Response(response: 422, description: 'Ошибка валидации'),
    ]
)]
#[OA\Delete(
    path: '/api/v1/images/{image}',
    summary: 'Удалить изображение',
    security: [['bearerAuth' => []]],
    tags: ['Product Images'],
    parameters: [
        new OA\Parameter(name: 'image', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
    ],
    responses: [
        new OA\Response(response: 200, description: 'Изображение удалено'),
        new OA\Response(response: 401, description: 'Не авторизован'),
    ]
)]
final class ProductImagesOpenApi {}
