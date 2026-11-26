<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Database\QueryException;

beforeEach(function (): void {
    if (getenv('DB_CONNECTION') !== 'pgsql') {
        $this->markTestSkipped('Группа pgsql запускается в CI с DB_CONNECTION=pgsql.');
    }
});

it('rejects stock quantity below reservation in PostgreSQL', function (): void {
    $store = Store::query()->create(['name' => 'Shop', 'slug' => 'shop-pg', 'is_active' => true]);
    $category = Category::query()->create(['store_id' => $store->id, 'name' => 'Phones', 'slug' => 'phones']);
    $product = Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'sku' => 'SKU-PG',
        'name' => 'Phone',
        'slug' => 'phone-pg',
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
    $stock = Stock::query()->create([
        'store_id' => $store->id,
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => 10,
        'reserved_quantity' => 4,
    ]);

    expect(static fn (): bool => $stock->update(['quantity' => 1]))->toThrow(QueryException::class);
});
