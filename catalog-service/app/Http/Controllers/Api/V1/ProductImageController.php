<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Product\UploadProductImageRequest;
use App\Http\Resources\ProductImageResource;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Products\CatalogCacheService;
use App\Services\Products\ProductImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class ProductImageController extends Controller
{
    public function __construct(
        private readonly ProductImageService $imageService,
        private readonly CatalogCacheService $cacheService,
    ) {}

    public function store(UploadProductImageRequest $request, Product $product): JsonResponse
    {
        $file = $request->file('image');
        if (! $file instanceof UploadedFile) {
            abort(422, 'Файл изображения обязателен');
        }

        $image = $this->imageService->upload(
            product: $product,
            file: $file,
            isPrimary: $request->boolean('is_primary', false),
        );

        $this->forgetProductCache($product);

        return new ProductImageResource($image)
            ->additional([
                'success' => true,
                'message' => 'Изображение успешно загружено',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(ProductImage $image): JsonResponse
    {
        $product = $image->product;
        $this->imageService->delete($image);

        if ($product instanceof Product) {
            $this->forgetProductCache($product);
        }

        return response()->json([
            'success' => true,
            'message' => 'Изображение удалено',
        ]);
    }

    private function forgetProductCache(Product $product): void
    {
        $this->cacheService->forgetProduct((int) $product->id);
        $this->cacheService->forgetProductList((int) $product->store_id);
    }
}
