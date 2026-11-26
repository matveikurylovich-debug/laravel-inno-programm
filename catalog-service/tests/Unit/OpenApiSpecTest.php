<?php

declare(strict_types=1);

use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\PathItem;
use OpenApi\Generator;

it('builds a valid OpenAPI specification from app/OpenApi classes', function (): void {
    $openApi = (new Generator)->generate([base_path('app/OpenApi')]);

    expect($openApi)->toBeInstanceOf(OpenApi::class);

    $paths = array_map(
        static fn (PathItem $item): string => $item->path,
        is_array($openApi?->paths) ? $openApi->paths : [],
    );

    expect($paths)->toContain(
        '/api/v1/stores',
        '/api/v1/stores/{storeId}/products',
        '/api/v1/stores/{storeId}/categories/tree',
        '/api/v1/products/{id}',
        '/api/v1/products',
        '/api/v1/categories',
        '/api/v1/products/{product}/images',
    );
});
