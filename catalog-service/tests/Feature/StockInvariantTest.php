<?php

declare(strict_types=1);

use App\Models\CatalogOutbox;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\StockReservation;
use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @return array{0: Store, 1: Product, 2: StoreLocation}
 */
function createStockShelf(int $quantity, int $reserved): array
{
    $store = Store::query()->create(['name' => 'Shop', 'slug' => 'shop-'.Str::random(4), 'is_active' => true]);
    $location = StoreLocation::query()->create([
        'store_id' => $store->id,
        'name' => 'Main',
        'address' => 'A',
        'is_active' => true,
    ]);
    $category = Category::query()->create([
        'store_id' => $store->id,
        'name' => 'Phones',
        'slug' => 'phones-'.Str::random(4),
    ]);
    $product = Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'sku' => 'SKU-'.Str::random(4),
        'name' => 'Phone',
        'slug' => 'phone-'.Str::random(4),
        'price' => 10,
        'is_active' => true,
        'attributes' => [],
    ]);
    Stock::query()->create([
        'store_id' => $store->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'reserved_quantity' => $reserved,
    ]);

    return [$store, $product, $location];
}

it('rejects setting stock below current reservation', function (): void {
    [, $product, $location] = createStockShelf(10, 4);

    $this->withToken(catalogJwt('admin'))
        ->putJson("/api/v1/products/{$product->id}/stock", [
            'quantity' => 3,
            'location_id' => $location->id,
        ])
        ->assertStatus(422);

    expect(Stock::query()->value('quantity'))->toBe(10);
});

it('prevents deleting a location that holds reservations', function (): void {
    [$store, $product, $location] = createStockShelf(10, 0);
    StockReservation::query()->create([
        'order_id' => (string) Str::uuid(),
        'store_id' => $store->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 1,
        'status' => 'reserved',
    ]);

    try {
        $location->delete();
        $this->fail('Удаление точки с резервом должно быть запрещено');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(409);
    }

    expect(StoreLocation::query()->find($location->id))->not->toBeNull();
});

it('republishes store products as inactive when deactivating store', function (): void {
    [$store, $product] = createStockShelf(5, 0);
    CatalogOutbox::query()->delete();

    $store->update(['is_active' => false]);

    $event = CatalogOutbox::query()->where('event', 'product.updated')->first();
    expect($event)->not->toBeNull()
        ->and($event?->payload['is_active'])->toBeFalse()
        ->and($event?->payload['product_id'])->toBe($product->id);
});
