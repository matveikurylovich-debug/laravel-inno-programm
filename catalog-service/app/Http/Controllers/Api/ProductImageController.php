<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\UploadProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CatalogCacheService;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class ProductImageController extends Controller
{
    public function __construct(
        protected ProductImageService $imageService,
        protected CatalogCacheService $cacheService,
    ) {}

    /**
     * Админка: Загрузка изображения товара в MinIO S3
     */
    #[OA\Post(
        path: '/api/v1/products/{product}/images',
        summary: 'Загрузить изображение товара',
        security: [['bearerAuth' => []]],
        tags: ['Product Images'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
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
        responses: [
            new OA\Response(response: 201, description: 'Изображение загружено'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    public function store(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $image = $this->imageService->upload(
            product: $product,
            file: $request->file('image'),
            isPrimary: $request->boolean('is_primary', false)
        );

        $this->forgetProductCache($product);

        return response()->json([
            'success' => true,
            'message' => 'Изображение успешно загружено',
            'data'    => $image,
        ], 201);
    }

    /**
     * Админка: Удаление изображения из MinIO S3 и БД
     */
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
    public function destroy(ProductImage $image): JsonResponse
    {
        $product = $image->product;
        $this->imageService->delete($image);

        if ($product) {
            $this->forgetProductCache($product);
        }

        return response()->json([
            'success' => true,
            'message' => 'Изображение удалено',
        ]);
    }

    private function forgetProductCache(Product $product): void
    {
        $this->cacheService->forgetProduct($product->id);
        $this->cacheService->forgetProductList($product->store_id);
    }
}
