<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StoreLocation;

function createTenantStore(string $slug): Store
{
    return Store::query()->create([
        'name' => $slug,
        'slug' => $slug,
        'is_active' => true,
    ]);
}

function createTenantProduct(Store $store, string $name): Product
{
    return Product::query()->create([
        'store_id' => $store->id,
        'name' => $name,
        'slug' => strtolower($name).'-'.$store->id,
        'sku' => strtoupper($name).'-'.$store->id,
        'price' => 10,
        'is_active' => true,
        'attributes' => [],
    ]);
}

it('isolates storefront products by store while listing all active stores', function (): void {
    $first = createTenantStore('one');
    $second = createTenantStore('two');
    createTenantProduct($first, 'Phone');
    createTenantProduct($second, 'Tablet');

    $this->getJson("/api/v1/stores/{$first->id}/products")
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.name', 'Phone');

    $this->getJson('/api/v1/stores')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('lets platform admin manage multiple stores explicitly', function (): void {
    $first = createTenantStore('first');
    $second = createTenantStore('second');
    $category = Category::query()->create([
        'store_id' => $second->id,
        'name' => 'Phones',
        'slug' => 'phones',
    ]);

    $this->withToken(catalogJwt('admin'))
        ->postJson('/api/v1/products', [
            'store_id' => $second->id,
            'category_id' => $category->id,
            'name' => 'Tablet',
            'price' => 20,
            'quantity' => 1,
        ])
        ->assertCreated()
        ->assertJsonPath('data.store_id', $second->id);

    expect(Product::query()->where('store_id', $second->id)->count())->toBe(1)
        ->and(Product::query()->where('store_id', $first->id)->count())->toBe(0);
});

it('rejects cross-store category and location references with 422', function (): void {
    $first = createTenantStore('store-a');
    $second = createTenantStore('store-b');

    $firstCategory = Category::query()->create([
        'store_id' => $first->id,
        'name' => 'First Cat',
        'slug' => 'first-cat',
    ]);
    $secondCategory = Category::query()->create([
        'store_id' => $second->id,
        'name' => 'Second Cat',
        'slug' => 'second-cat',
    ]);
    $secondLocation = StoreLocation::query()->create([
        'store_id' => $second->id,
        'name' => 'Foreign Pickup',
        'address' => 'Street 2',
        'is_active' => true,
    ]);

    $product = Product::query()->create([
        'store_id' => $first->id,
        'category_id' => $firstCategory->id,
        'sku' => 'SKU-A1',
        'name' => 'Phone A',
        'slug' => 'phone-a',
        'price' => 50,
        'is_active' => true,
        'attributes' => [],
    ]);

    $token = catalogJwt('admin');

    $this->withToken($token)
        ->postJson('/api/v1/products', [
            'store_id' => $first->id,
            'category_id' => $secondCategory->id,
            'name' => 'Invalid Cross Store',
            'price' => 30,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');

    $this->withToken($token)
        ->patchJson("/api/v1/products/{$product->id}", [
            'category_id' => $secondCategory->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category_id');

    $this->withToken($token)
        ->putJson("/api/v1/products/{$product->id}/stock", [
            'quantity' => 5,
            'location_id' => $secondLocation->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('location_id');

    $this->withToken($token)
        ->patchJson("/api/v1/categories/{$firstCategory->id}", [
            'parent_id' => $secondCategory->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});
