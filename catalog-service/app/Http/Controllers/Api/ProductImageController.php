<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\UploadProductImageRequest;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ProductImageService;
use Illuminate\Http\JsonResponse;

class ProductImageController extends Controller
{
    public function __construct(
        protected ProductImageService $imageService
    ) {}

    /**
     * Админка: Загрузка изображения товара в MinIO S3
     */
    public function store(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $image = $this->imageService->upload(
            product: $product,
            file: $request->file('image'),
            isPrimary: $request->boolean('is_primary', false)
        );

        return response()->json([
            'success' => true,
            'message' => 'Изображение успешно загружено',
            'data'    => $image,
        ], 201);
    }

    /**
     * Админка: Удаление изображения из MinIO S3 и БД
     */
    public function destroy(ProductImage $image): JsonResponse
    {
        $this->imageService->delete($image);

        return response()->json([
            'success' => true,
            'message' => 'Изображение удалено',
        ]);
    }
}