<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Product\StoreProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StoreLocation;
use App\Services\CatalogCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class ProductController extends Controller
{
    public function __construct(
        protected CatalogCacheService $cacheService
    ) {}

    /**
     * Витрина: Каталог с фильтрами по цене, категории, бренду (JSONB) и пагинацией
     */
    #[OA\Get(
        path: '/api/v1/stores/{storeId}/products',
        summary: 'Список товаров магазина',
        description: 'Каталог с фильтрами по цене, категории, бренду и пагинацией',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'storeId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'category_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'price_from', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'price_to', in: 'query', required: false, schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'brand', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['created_at', 'price', 'name'])),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['asc', 'desc'])),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', default: 20, maximum: 100)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Список товаров'),
        ]
    )]
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
                'data' => collect($products->items())->map(function (Product $product): array {
                    $data = $product->toArray();
                    $data['available_stock'] = $product->available_stock;
                    $data['total_available'] = $product->available_stock;

                    return $data;
                })->values()->all(),
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
     * Витрина: карточка товара и наличие только в точках его магазина.
     */
    #[OA\Get(
        path: '/api/v1/products/{id}',
        summary: 'Карточка товара',
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Товар с наличием по точкам'),
            new OA\Response(response: 404, description: 'Товар не найден'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $product = Product::query()
            ->with(['images', 'category', 'store', 'stock'])
            ->find($id);

        if ($product === null || ! $product->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Товар не найден',
            ], 404);
        }

        $stocks = $product->stock->keyBy('location_id');
        $locations = StoreLocation::query()
            ->where('store_id', $product->store_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (StoreLocation $location) use ($stocks): array {
                $stock = $stocks->get($location->id);
                $available = $stock instanceof Stock
                    ? max(0, $stock->quantity - $stock->reserved_quantity)
                    : 0;

                return [
                    'location_id' => $location->id,
                    'store_id' => $location->store_id,
                    'name' => $location->name,
                    'city' => $location->city,
                    'address' => $location->address,
                    'phone' => $location->phone,
                    'working_hours' => $location->working_hours,
                    'available' => $available,
                    'is_in_stock' => $available > 0,
                ];
            })
            ->values();

        $data = $product->toArray();
        $data['available_stock'] = $product->available_stock;
        $data['total_available'] = $product->available_stock;
        $data['locations'] = $locations;

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Админка: Создание товара с инициализацией склада в транзакции
     */
    #[OA\Post(
        path: '/api/v1/products',
        summary: 'Создать товар',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['store_id', 'category_id', 'name', 'price'],
                properties: [
                    new OA\Property(property: 'store_id', type: 'integer'),
                    new OA\Property(property: 'category_id', type: 'integer'),
                    new OA\Property(property: 'sku', type: 'string', nullable: true),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'price', type: 'number'),
                    new OA\Property(property: 'attributes', type: 'object', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean', nullable: true),
                    new OA\Property(property: 'quantity', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Товар создан'),
            new OA\Response(response: 401, description: 'Не авторизован'),
            new OA\Response(response: 422, description: 'Ошибка валидации'),
        ]
    )]
    public function store(StoreProductRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['slug'] = $validated['slug'] ?? Str::slug($validated['name']);
        $validated['sku'] = $validated['sku'] ?? Str::upper(Str::slug($validated['name'], '')).'-'.Str::upper(Str::random(4));

        $product = DB::transaction(function () use ($validated) {
            $product = Product::create($validated);

            $locationId = StoreLocation::query()
                ->where('store_id', $product->store_id)
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');

            Stock::create([
                'product_id' => $product->id,
                'store_id' => $product->store_id,
                'location_id' => $locationId,
                'quantity' => $validated['quantity'] ?? 0,
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
    #[OA\Put(
        path: '/api/v1/products/{product}',
        summary: 'Обновить товар',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'category_id', type: 'integer'),
                    new OA\Property(property: 'name', type: 'string'),
                    new OA\Property(property: 'slug', type: 'string', nullable: true),
                    new OA\Property(property: 'description', type: 'string', nullable: true),
                    new OA\Property(property: 'price', type: 'number'),
                    new OA\Property(property: 'attributes', type: 'object', nullable: true),
                    new OA\Property(property: 'is_active', type: 'boolean'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Товар обновлён'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[OA\Patch(
        path: '/api/v1/products/{product}',
        summary: 'Частично обновить товар',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Товар обновлён'),
        ]
    )]
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
    #[OA\Delete(
        path: '/api/v1/products/{product}',
        summary: 'Удалить товар',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Товар удалён'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
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
    #[OA\Put(
        path: '/api/v1/products/{product}/stock',
        summary: 'Обновить остаток товара',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['quantity'],
                properties: [
                    new OA\Property(property: 'quantity', type: 'integer', minimum: 0),
                    new OA\Property(property: 'location_id', type: 'integer', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Остаток обновлён'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[OA\Patch(
        path: '/api/v1/products/{product}/stock',
        summary: 'Частично обновить остаток товара',
        security: [['bearerAuth' => []]],
        tags: ['Products'],
        parameters: [
            new OA\Parameter(name: 'product', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Остаток обновлён'),
        ]
    )]
    public function updateStock(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'location_id' => ['nullable', 'integer'],
        ]);

        $locationId = $validated['location_id'] ?? StoreLocation::query()
            ->where('store_id', $product->store_id)
            ->orderBy('id')
            ->value('id');

        $stock = Stock::firstOrCreate(
            ['product_id' => $product->id, 'location_id' => $locationId],
            ['store_id' => $product->store_id, 'quantity' => 0, 'reserved_quantity' => 0],
        );

        $stock->update(['quantity' => $validated['quantity']]);

        return response()->json([
            'success' => true,
            'message' => 'Остаток обновлен',
            'data' => $stock->fresh(),
        ]);
    }
}
