<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Category\StoreCategoryRequest;
use App\Http\Requests\Api\V1\Category\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\Products\CatalogCacheService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CatalogCacheService $cacheService,
    ) {}

    public function tree(int $storeId): JsonResponse
    {
        $categories = $this->cacheService->getCategoryTree($storeId);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::query()->create($request->validated());

        return new CategoryResource($category)
            ->additional([
                'success' => true,
                'message' => 'Категория успешно создана',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $category->update($request->sanitized());

        return new CategoryResource($category)
            ->additional([
                'success' => true,
                'message' => 'Категория обновлена',
            ])
            ->response();
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Категория удалена',
        ]);
    }
}
