<?php

declare(strict_types=1);

use App\Console\Commands\RelayOutboxCommand;
use App\Messaging\Producers\CatalogEventsProducer;
use App\Models\CatalogOutbox;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\StoreLocation;
use App\Services\Products\CatalogCacheService;
use App\Services\Stock\StockReservationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Junges\Kafka\Facades\Kafka;

/**
 * @return array{0: Store, 1: Product}
 */
function seedStorefrontCatalog(): array
{
    $store = Store::query()->create([
        'name' => 'Shop',
        'slug' => 'shop',
        'is_active' => true,
    ]);
    $parent = Category::query()->create([
        'store_id' => $store->id,
        'name' => 'Phones',
        'slug' => 'phones',
    ]);
    Category::query()->create([
        'store_id' => $store->id,
        'parent_id' => $parent->id,
        'name' => 'iOS',
        'slug' => 'ios',
    ]);
    $product = Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $parent->id,
        'sku' => 'SKU-1',
        'name' => 'Phone',
        'slug' => 'phone',
        'price' => 10,
        'is_active' => true,
        'attributes' => ['brand' => 'Acme'],
    ]);
    $location = StoreLocation::query()->create([
        'store_id' => $store->id,
        'name' => 'Main',
        'address' => 'A',
        'is_active' => true,
    ]);
    Stock::query()->create([
        'store_id' => $store->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'reserved_quantity' => 0,
    ]);

    return [$store, $product];
}

it('serves /up health check and disables swagger UI outside local environment', function (): void {
    $this->get('/up')->assertOk();
    $this->get('/api/documentation')->assertNotFound();
});

it('lists public store tree and product card with cached product lookup', function (): void {
    [$store, $product] = seedStorefrontCatalog();

    $this->getJson('/api/v1/stores')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'shop');

    $this->getJson("/api/v1/stores/{$store->id}/categories/tree")
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'phones')
        ->assertJsonPath('data.0.all_children.0.slug', 'ios');

    $this->getJson("/api/v1/stores/{$store->id}/products")
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Phone')
        ->assertJsonPath('meta.total', 1);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('data.slug', 'phone');

    expect(Cache::has(CatalogCacheService::productCardKey((int) $product->id)))->toBeTrue();
});

it('rejects invalid pagination parameters such as per_page=-1 with 422', function (): void {
    [$store] = seedStorefrontCatalog();

    $this->getJson("/api/v1/stores/{$store->id}/products?per_page=-1")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');

    $this->getJson("/api/v1/stores/{$store->id}/products?per_page=101")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('per_page');

    $this->getJson("/api/v1/stores/{$store->id}/products?page=0")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('page');
});

it('requires admin access token on write routes and rejects customer token with 403', function (): void {
    [$store, $product] = seedStorefrontCatalog();
    $customerToken = catalogJwt('customer');

    $this->postJson('/api/v1/stores', ['name' => 'New'])
        ->assertUnauthorized();

    $this->withToken($customerToken)
        ->postJson('/api/v1/stores', ['name' => 'New'])
        ->assertForbidden();

    $this->withToken($customerToken)
        ->postJson('/api/v1/categories', ['store_id' => $store->id, 'name' => 'Forbidden'])
        ->assertForbidden();

    $this->withToken($customerToken)
        ->postJson('/api/v1/products', [
            'store_id' => $store->id,
            'category_id' => $product->category_id,
            'name' => 'Forbidden',
            'price' => 10,
        ])
        ->assertForbidden();

    $this->withToken($customerToken)
        ->patchJson("/api/v1/products/{$product->id}", ['name' => 'Nope'])
        ->assertForbidden();

    $this->withToken($customerToken)
        ->deleteJson("/api/v1/products/{$product->id}")
        ->assertForbidden();
});

it('validates duplicate slug and sku on product update with 422 instead of 500', function (): void {
    [$store, $first] = seedStorefrontCatalog();
    $second = Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $first->category_id,
        'sku' => 'SKU-2',
        'name' => 'Tablet',
        'slug' => 'tablet',
        'price' => 25,
        'is_active' => true,
        'attributes' => [],
    ]);

    $adminToken = catalogJwt('admin');

    $this->withToken($adminToken)
        ->patchJson("/api/v1/products/{$second->id}", ['slug' => 'phone'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');

    $this->withToken($adminToken)
        ->patchJson("/api/v1/products/{$second->id}", ['sku' => 'SKU-1'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('sku');

    $this->withToken($adminToken)
        ->patchJson("/api/v1/products/{$second->id}", [
            'slug' => 'tablet',
            'sku' => 'SKU-2',
            'attributes' => ['nested' => ['bad']],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('attributes');

    $this->withToken($adminToken)
        ->patchJson("/api/v1/products/{$second->id}", [
            'slug' => 'tablet',
            'sku' => 'SKU-2',
            'name' => 'Tablet Pro',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Tablet Pro');
});

it('prevents category parent_id cycles and duplicate slugs on category update', function (): void {
    $store = Store::query()->create(['name' => 'Cycle Shop', 'slug' => 'cycle-shop', 'is_active' => true]);
    $root = Category::query()->create(['store_id' => $store->id, 'name' => 'Root', 'slug' => 'root']);
    $child = Category::query()->create([
        'store_id' => $store->id,
        'parent_id' => $root->id,
        'name' => 'Child',
        'slug' => 'child',
    ]);
    $grandchild = Category::query()->create([
        'store_id' => $store->id,
        'parent_id' => $child->id,
        'name' => 'Grandchild',
        'slug' => 'grandchild',
    ]);

    $adminToken = catalogJwt('admin');

    $this->withToken($adminToken)
        ->patchJson("/api/v1/categories/{$root->id}", ['parent_id' => $root->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    $this->withToken($adminToken)
        ->patchJson("/api/v1/categories/{$root->id}", ['parent_id' => $grandchild->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    $this->withToken($adminToken)
        ->patchJson("/api/v1/categories/{$child->id}", ['slug' => 'root'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('creates store category and product with initial stock and outbox event', function (): void {
    $store = Store::query()->create([
        'name' => 'Shop',
        'slug' => 'shop',
        'is_active' => true,
    ]);
    $rootCategory = Category::query()->create([
        'store_id' => $store->id,
        'name' => 'Electronics',
        'slug' => 'electronics',
    ]);
    $category = Category::query()->create([
        'store_id' => $store->id,
        'parent_id' => $rootCategory->id,
        'name' => 'Phones',
        'slug' => 'phones',
    ]);

    expect($category->pathIds())->toBe([$rootCategory->id, $category->id]);

    $adminToken = catalogJwt('admin');

    $this->withToken($adminToken)
        ->postJson('/api/v1/products', [
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Tablet',
            'price' => 20,
            'quantity' => 3,
            'attributes' => ['brand' => ['nested']],
        ])
        ->assertUnprocessable();

    $this->withToken($adminToken)
        ->postJson('/api/v1/products', [
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Tablet',
            'price' => 20,
            'quantity' => 3,
            'attributes' => ['brand' => 'Acme'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Tablet');

    $productId = Product::query()->where('slug', 'tablet')->value('id');
    expect(Stock::query()->where('product_id', $productId)->value('quantity'))->toBe(3);

    $created = CatalogOutbox::query()->where('event', 'product.created')->first();
    expect($created)->not->toBeNull()
        ->and($created?->payload['is_active'])->toBeTrue()
        ->and($created?->payload['available'])->toBe(3)
        ->and($created?->payload['category_path'])->toBe([$rootCategory->id, $category->id]);

    resolve(RelayOutboxCommand::class)->publishBatch(10);
    Kafka::assertPublishedOn('catalog.events');
});

it('does not persist outbox event or invalidate cache when transaction rolls back', function (): void {
    [$store, $product] = seedStorefrontCatalog();

    $this->getJson("/api/v1/products/{$product->id}")->assertOk();
    $versionBefore = (int) Cache::get(CatalogCacheService::productListVersionKey((int) $store->id), 1);

    try {
        DB::transaction(function () use ($product): void {
            $product->update(['name' => 'Rolled Back']);
            resolve(CatalogEventsProducer::class)->productDeleted((int) $product->id, (int) $product->store_id);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    expect(CatalogOutbox::query()->where('event', 'product.deleted')->count())->toBe(0)
        ->and((int) Cache::get(CatalogCacheService::productListVersionKey((int) $store->id), 1))->toBe($versionBefore)
        ->and(Cache::has(CatalogCacheService::productCardKey((int) $product->id)))->toBeTrue();
});

it('requires category parent to belong to the same store on creation', function (): void {
    $first = Store::query()->create(['name' => 'A', 'slug' => 'a', 'is_active' => true]);
    $second = Store::query()->create(['name' => 'B', 'slug' => 'b', 'is_active' => true]);
    $parent = Category::query()->create([
        'store_id' => $second->id,
        'name' => 'Other',
        'slug' => 'other',
    ]);

    $this->withToken(catalogJwt('admin'))
        ->postJson('/api/v1/categories', [
            'store_id' => $first->id,
            'parent_id' => $parent->id,
            'name' => 'Child',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});

it('updates stock and uploads image as admin', function (): void {
    [, $product] = seedStorefrontCatalog();
    Storage::fake('minio');

    $this->withToken(catalogJwt('admin'))
        ->patchJson("/api/v1/products/{$product->id}/stock", ['quantity' => 7])
        ->assertOk()
        ->assertJsonPath('data.quantity', 7);

    $jpegContent = base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=');

    $this->withToken(catalogJwt('admin'))
        ->post("/api/v1/products/{$product->id}/images", [
            'image' => UploadedFile::fake()->createWithContent('phone.jpg', $jpegContent),
            'is_primary' => true,
        ])
        ->assertCreated();

    expect(Storage::disk('minio')->allFiles('products/'.$product->id))->not->toBeEmpty();
});

it('keeps storefront JSON contract fields intact', function (): void {
    [$store, $product] = seedStorefrontCatalog();

    $this->getJson("/api/v1/stores/{$store->id}/products")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.0.id', $product->id)
        ->assertJsonPath('data.0.name', 'Phone')
        ->assertJsonPath('data.0.price', '10.00')
        ->assertJsonPath('data.0.slug', 'phone')
        ->assertJsonPath('data.0.available_stock', 10)
        ->assertJsonPath('data.0.primary_image', null)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.per_page', 20)
        ->assertJsonPath('meta.total', 1);

    $this->getJson("/api/v1/products/{$product->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $product->id)
        ->assertJsonPath('data.name', 'Phone')
        ->assertJsonPath('data.price', '10.00')
        ->assertJsonPath('data.slug', 'phone')
        ->assertJsonPath('data.available_stock', 10)
        ->assertJsonPath('data.total_available', 10)
        ->assertJsonPath('data.images', [])
        ->assertJsonPath('data.locations.0.available', 10);
});

it('republishes subtree products with updated category_path when moving category', function (): void {
    $store = Store::query()->create(['name' => 'Shop', 'slug' => 'shop-path', 'is_active' => true]);
    $root = Category::query()->create(['store_id' => $store->id, 'name' => 'Phones', 'slug' => 'phones-path']);
    $child = Category::query()->create([
        'store_id' => $store->id,
        'parent_id' => $root->id,
        'name' => 'iOS',
        'slug' => 'ios-path',
    ]);
    $grand = Category::query()->create([
        'store_id' => $store->id,
        'parent_id' => $child->id,
        'name' => 'Pro',
        'slug' => 'pro-path',
    ]);
    $other = Category::query()->create(['store_id' => $store->id, 'name' => 'Tablets', 'slug' => 'tablets-path']);
    $product = Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $child->id,
        'sku' => 'SKU-PATH',
        'name' => 'Phone',
        'slug' => 'phone-path',
        'price' => 10,
        'is_active' => true,
        'attributes' => [],
    ]);
    $nested = Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $grand->id,
        'sku' => 'SKU-NESTED',
        'name' => 'Nested',
        'slug' => 'nested-path',
        'price' => 12,
        'is_active' => true,
        'attributes' => [],
    ]);
    Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $root->id,
        'sku' => 'SKU-ROOT',
        'name' => 'Root',
        'slug' => 'root-path',
        'price' => 8,
        'is_active' => true,
        'attributes' => [],
    ]);
    CatalogOutbox::query()->delete();

    $this->withToken(catalogJwt('admin'))
        ->patchJson("/api/v1/categories/{$child->id}", ['parent_id' => $other->id])
        ->assertOk();

    $events = CatalogOutbox::query()->where('event', 'product.updated')->get()->keyBy(
        static fn (CatalogOutbox $event): int => (int) $event->payload['product_id'],
    );

    expect($events)->toHaveCount(2)
        ->and($events->get($product->id)?->payload['category_path'])->toBe([$other->id, $child->id])
        ->and($events->get($nested->id)?->payload['category_path'])->toBe([$other->id, $child->id, $grand->id]);
});

it('reindexes products cleanly via ProductEventPublisher command', function (): void {
    [, $product] = seedStorefrontCatalog();
    CatalogOutbox::query()->delete();

    $this->artisan('catalog:reindex-products')->assertSuccessful();

    $event = CatalogOutbox::query()->where('event', 'product.updated')->first();
    expect($event)->not->toBeNull()
        ->and($event?->payload['product_id'])->toBe($product->id);
});

it('reports stock-not-found reason when reserving missing product', function (): void {
    $store = Store::query()->create(['name' => 'Shop', 'slug' => 'shop-missing', 'is_active' => true]);

    expect(resolve(StockReservationService::class)->reserve((string) Str::uuid(), $store->id, [
        ['product_id' => 999999, 'quantity' => 1],
    ]))->toBeFalse();

    $failed = CatalogOutbox::query()->where('event', 'stock.reservation_failed')->first();
    expect($failed)->not->toBeNull()
        ->and($failed?->payload['reason'])->toBe('stock-not-found');
});
