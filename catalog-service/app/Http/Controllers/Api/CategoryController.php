<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Category\StoreCategoryRequest;
use App\Models\Category;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class CategoryController extends Controller
{
    public function __construct(
        protected CatalogCacheService $cacheService
    ) {}

    /**
     * Витрина: Дерево категорий магазина из Redis
     */
    #[OA\Get(
        path: '/api/v1/stores/{storeId}/categories/tree',
        summary: 'Дерево категорий магазина',
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'storeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Дерево категорий'),
        ]
    )]
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
    #[OA\Post(
        path: '/api/v1/categories',
        summary: 'Создать категорию',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['store_id', 'name'],
                properties: [
                    new OA\Property(property: 'store_id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'position', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Категория создана'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
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
    #[OA\Put(
        path: '/api/v1/categories/{category}',
        summary: 'Обновить категорию',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'parent_id', type: 'integer', nullable: true),
                    new OA\Property(property: 'position', type: 'integer'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Категория обновлена'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    #[OA\Patch(
        path: '/api/v1/categories/{category}',
        summary: 'Частично обновить категорию',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Категория обновлена'),
        ]
    )]
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

    #[OA\Delete(
        path: '/api/v1/categories/{category}',
        summary: 'Удалить категорию',
        security: [['bearerAuth' => []]],
        tags: ['Categories'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Категория удалена'),
        ]
    )]
    public function destroy(Category $category): JsonResponse
    {
        $category->delete(); // CategoryObserver автоматически сбросит кэш в Redis

        return response()->json([
            'success' => true,
            'message' => 'Категория удалена',
        ]);
    }
}
