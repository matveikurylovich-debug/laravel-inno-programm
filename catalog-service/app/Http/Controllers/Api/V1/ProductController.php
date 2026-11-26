<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Product\IndexProductsRequest;
use App\Http\Requests\Api\V1\Product\StoreProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateProductRequest;
use App\Http\Requests\Api\V1\Product\UpdateStockRequest;
use App\Http\Resources\ProductResource;
use App\Http\Resources\StockResource;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StoreLocation;
use App\Services\Products\CatalogCacheService;
use App\Services\Products\CreateProduct;
use App\Services\Stock\StockAvailability;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function __construct(
        private readonly CatalogCacheService $cacheService,
        private readonly CreateProduct $createProduct,
        private readonly StockAvailability $stockAvailability,
    ) {}

    public function index(IndexProductsRequest $request, int $storeId): JsonResponse
    {
        $filters = $request->normalizedFilters();

        $payload = $this->cacheService->rememberProductList($storeId, $filters, function () use ($storeId, $filters): array {
            $products = Product::query()
                ->forStore($storeId)
                ->active()
                ->filter([
                    'store_id' => $storeId,
                    'category_id' => $filters['category_id'],
                ])
                ->with(['primaryImage', 'stock', 'category'])
                ->paginate($filters['per_page'], ['*'], 'page', $filters['page']);

            /** @var array<string, mixed> $data */
            $data = ProductResource::collection($products)
                ->additional(['success' => true])
                ->response()
                ->getData(true);

            return $data;
        });

        return response()->json($payload);
    }

    public function show(int $id): JsonResponse
    {
        $payload = $this->cacheService->rememberProduct($id, function () use ($id): ?array {
            $product = Product::query()
                ->with(['images', 'category', 'store', 'stock'])
                ->find($id);

            $storeIsActive = $product !== null && $product->store !== null && (bool) $product->store->is_active;
            if ($product === null || ! $product->is_active || ! $storeIsActive) {
                return null;
            }

            $stocks = $product->stock->keyBy('location_id');
            $locations = StoreLocation::query()
                ->where('store_id', $product->store_id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(static function (StoreLocation $location) use ($stocks): array {
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

            $product->setAttribute('locations', $locations->all());

            /** @var array<string, mixed> $data */
            $data = new ProductResource($product)
                ->additional(['success' => true])
                ->response()
                ->getData(true);

            return $data;
        });

        if ($payload === null) {
            abort(404, 'Товар не найден');
        }

        return response()->json($payload);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        /** @var array<string, mixed> $validated */
        $validated = $request->validated();
        $product = $this->createProduct->handle($validated);

        return new ProductResource($product->load(['stock', 'category']))
            ->additional([
                'success' => true,
                'message' => 'Товар успешно создан',
            ])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $product->update($request->sanitized());

        return new ProductResource($product)
            ->additional([
                'success' => true,
                'message' => 'Товар обновлен',
            ])
            ->response();
    }

    public function destroy(Product $product): JsonResponse
    {
        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Товар удален',
        ]);
    }

    public function updateStock(UpdateStockRequest $request, Product $product): JsonResponse
    {
        /** @var array{quantity: int|string, location_id?: int|string|null} $validated */
        $validated = $request->validated();
        $quantity = (int) $validated['quantity'];

        $rawLocationId = $validated['location_id'] ?? StoreLocation::query()
            ->where('store_id', $product->store_id)
            ->orderBy('id')
            ->value('id');

        if (! is_numeric($rawLocationId)) {
            abort(422, 'У магазина нет точки самовывоза');
        }

        $locationId = (int) $rawLocationId;
        $rejected = false;
        $stockId = null;

        DB::transaction(function () use ($product, $locationId, $quantity, &$rejected, &$stockId): void {
            $stock = Stock::query()
                ->where('product_id', $product->id)
                ->where('location_id', $locationId)
                ->lockForUpdate()
                ->first();

            $stock ??= Stock::query()->create([
                'product_id' => $product->id,
                'location_id' => $locationId,
                'store_id' => $product->store_id,
                'quantity' => 0,
                'reserved_quantity' => 0,
            ]);

            if ($quantity < (int) $stock->reserved_quantity) {
                $rejected = true;

                return;
            }

            $stock->update(['quantity' => $quantity]);
            $stockId = (int) $stock->id;
            $this->stockAvailability->publish((int) $product->id);
        });

        if ($rejected) {
            abort(422, 'Остаток не может быть меньше резерва');
        }

        return new StockResource(Stock::query()->findOrFail($stockId))
            ->additional([
                'success' => true,
                'message' => 'Остаток обновлен',
            ])
            ->response();
    }
}
