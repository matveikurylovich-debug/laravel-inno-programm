<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function __construct(
        protected CatalogCacheService $cacheService
    ) {}

    /**
     * Витрина: Каталог с фильтрами по цене, категории, бренду (JSONB) и пагинацией
     */
    public function index(Request $request, int $storeId): JsonResponse
    {
        $filters = [
            'category_id' => $request->input('category_id'),
            'price_from' => $request->input('price_from'),
            'price_to' => $request->input('price_to'),
            'q' => $request->input('q'),
            'brand' => $request->input('brand'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_order' => $request->input('sort_order', 'desc'),
            'page' => $request->input('page', 1),
            'per_page' => min((int) $request->input('per_page', 20), 100),
        ];

        $payload = $this->cacheService->rememberProductList($storeId, md5(json_encode($filters)), function () use ($storeId, $filters) {
            $query = Product::forStore($storeId)
                ->where('is_active', true)
                ->with(['primaryImage', 'stock', 'category']);

            if ($filters['category_id']) {
                $query->whereIn(
                    'category_id',
                    Category::idsIncludingDescendants($storeId, (int) $filters['category_id']),
                );
            }

            if ($filters['price_from'] !== null && $filters['price_from'] !== '') {
                $query->where('price', '>=', (float) $filters['price_from']);
            }
            if ($filters['price_to'] !== null && $filters['price_to'] !== '') {
                $query->where('price', '<=', (float) $filters['price_to']);
            }

            if ($filters['q']) {
                $search = $filters['q'];
                $like = DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
                $query->where(function ($q) use ($search, $like) {
                    $q->where('name', $like, "%{$search}%")
                        ->orWhere('description', $like, "%{$search}%");
                });
            }

            if ($filters['brand']) {
                $query->where('attributes->brand', $filters['brand']);
            }

            $sortBy = in_array($filters['sort_by'], ['created_at', 'price', 'name'], true)
                ? $filters['sort_by']
                : 'created_at';
            $sortOrder = $filters['sort_order'] === 'asc' ? 'asc' : 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $products = $query->paginate($filters['per_page']);

            return [
                'data' => collect($products->items())->map(fn (Product $product) => $product->toArray())->values()->all(),
                'meta' => [
                    'current_page' => $products->currentPage(),
                    'last_page' => $products->lastPage(),
                    'per_page' => $products->perPage(),
                    'total' => $products->total(),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $payload['data'],
            'meta' => $payload['meta'],
        ]);
    }

    /**
     * Витрина: Получение карточки товара из Redis
     */
    public function show(int $id): JsonResponse
    {
        $product = $this->cacheService->getProduct($id);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Товар не найден',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => $product,
        ]);
    }

    /**
     * Админка: Создание товара с инициализацией склада в транзакции
     */
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['sku'] = $validated['sku'] ?? Str::upper(Str::slug($validated['name'], '')).'-'.Str::upper(Str::random(4));

        $product = DB::transaction(function () use ($validated) {
            $product = Product::create($validated);

            Stock::create([
                'product_id'        => $product->id,
                'store_id'          => $product->store_id,
                'quantity'          => $validated['quantity'] ?? 0,
                'reserved_quantity' => 0,
            ]);

            return $product;
        });

        return response()->json([
            'success' => true,
            'message' => 'Товар успешно создан',
            'data'    => $product->load('stock'),
        ], 201);
    }

    /**
     * Админка: Обновление информации о товаре
     */
    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'name'        => ['sometimes', 'string', 'max:255'],
            'slug'        => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'attributes'  => ['nullable', 'array'],
            'is_active'   => ['sometimes', 'boolean'],
        ]);

        $product->update($validated); // ProductObserver сбросит кэш в Redis

        return response()->json([
            'success' => true,
            'message' => 'Товар обновлен',
            'data'    => $product,
        ]);
    }

    /**
     * Админка: Удаление товара
     */
    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Товар удален',
        ]);
    }

    /**
     * Админка: изменение физического остатка.
     */
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
        ]);

        $stock = Stock::firstOrCreate(
            ['product_id' => $product->id, 'store_id' => $product->store_id],
            ['quantity' => 0, 'reserved_quantity' => 0],
        );

        $stock->update(['quantity' => $validated['quantity']]);

        return response()->json([
            'success' => true,
            'message' => 'Остаток обновлен',
            'data' => $stock->fresh(),
        ]);
    }
}