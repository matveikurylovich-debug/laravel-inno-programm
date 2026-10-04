<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Category\StoreCategoryRequest;
use App\Models\Category;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function __construct(
        protected CatalogCacheService $cacheService
    ) {}

    /**
     * Витрина: Дерево категорий магазина из Redis
     */
    public function tree(int $storeId): JsonResponse
    {
        $categories = $this->cacheService->getCategoryTree($storeId);

        return response()->json([
            'success' => true,
            'data'    => $categories,
        ]);
    }

    /**
     * Админка: Создание категории
     */
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Категория успешно создана',
            'data'    => $category,
        ], 201);
    }

    /**
     * Админка: Удаление категории
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:categories,id'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        if (array_key_exists('name', $data) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        if (! empty($data['parent_id'])) {
            $parent = Category::query()->find($data['parent_id']);
            if ($parent && (int) $parent->store_id !== (int) $category->store_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Родительская категория принадлежит другому магазину',
                ], 422);
            }
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Категория обновлена',
            'data' => $category,
        ]);
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete(); // CategoryObserver автоматически сбросит кэш в Redis

        return response()->json([
            'success' => true,
            'message' => 'Категория удалена',
        ]);
    }
}