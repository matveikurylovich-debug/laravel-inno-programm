<?php

declare(strict_types=1);

use App\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Filament\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\AdminUser;
use App\Models\CatalogOutbox;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use Livewire\Livewire;

it('creates product via Filament CreateProduct using shared CreateProduct service', function (): void {
    $admin = AdminUser::factory()->create();
    $store = Store::query()->create(['name' => 'Shop', 'slug' => 'shop-fil', 'is_active' => true]);
    $category = Category::query()->create(['store_id' => $store->id, 'name' => 'Phones', 'slug' => 'phones-fil']);

    Livewire::actingAs($admin)
        ->test(CreateProduct::class)
        ->fillForm([
            'store_id' => $store->id,
            'category_id' => $category->id,
            'name' => 'Filament Phone',
            'price' => 199.99,
            'quantity' => 5,
            'is_active' => true,
            'description' => 'Created from Filament',
            'attributes' => ['brand' => 'Acme'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $product = Product::query()->where('slug', 'filament-phone')->first();
    expect($product)->not->toBeNull()
        ->and($product?->sku)->not->toBeEmpty()
        ->and(Stock::query()->where('product_id', $product?->id)->value('quantity'))->toBe(5);

    $outbox = CatalogOutbox::query()->where('event', 'product.created')->first();
    expect($outbox)->not->toBeNull()
        ->and($outbox?->payload['available'])->toBe(5);
});

it('disables store_id on Filament EditProduct, keeps existing SKU when empty, and rejects duplicate slug', function (): void {
    $admin = AdminUser::factory()->create();
    $store = Store::query()->create(['name' => 'Shop', 'slug' => 'shop-edit', 'is_active' => true]);
    $category = Category::query()->create(['store_id' => $store->id, 'name' => 'Phones', 'slug' => 'phones-edit']);

    $product = Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'sku' => 'KEEP-SKU-01',
        'name' => 'Original Phone',
        'slug' => 'original-phone',
        'price' => 100,
        'is_active' => true,
        'attributes' => ['brand' => 'Acme'],
    ]);

    Product::query()->create([
        'store_id' => $store->id,
        'category_id' => $category->id,
        'sku' => 'TAKEN-SKU-02',
        'name' => 'Taken Phone',
        'slug' => 'taken-slug',
        'price' => 120,
        'is_active' => true,
        'attributes' => [],
    ]);

    Livewire::actingAs($admin)
        ->test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->assertFormFieldIsDisabled('store_id')
        ->fillForm([
            'name' => 'Updated Phone',
            'sku' => '',
            'slug' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $product->refresh();
    expect($product->name)->toBe('Updated Phone')
        ->and($product->sku)->toBe('KEEP-SKU-01')
        ->and($product->slug)->toBe('original-phone');

    Livewire::actingAs($admin)
        ->test(EditProduct::class, ['record' => $product->getRouteKey()])
        ->fillForm([
            'slug' => 'taken-slug',
        ])
        ->call('save')
        ->assertHasFormErrors(['slug']);
});

it('disables store_id and excludes self and descendants from parent_id on Filament EditCategory', function (): void {
    $admin = AdminUser::factory()->create();
    $store = Store::query()->create(['name' => 'Shop', 'slug' => 'shop-cat', 'is_active' => true]);
    $root = Category::query()->create(['store_id' => $store->id, 'name' => 'Root', 'slug' => 'root']);
    $child = Category::query()->create([
        'store_id' => $store->id,
        'parent_id' => $root->id,
        'name' => 'Child',
        'slug' => 'child',
    ]);
    $sibling = Category::query()->create(['store_id' => $store->id, 'name' => 'Sibling', 'slug' => 'sibling']);

    Livewire::actingAs($admin)
        ->test(EditCategory::class, ['record' => $root->getRouteKey()])
        ->assertFormFieldIsDisabled('store_id')
        ->fillForm([
            'parent_id' => $child->id,
        ])
        ->call('save')
        ->assertHasFormErrors(['parent_id'])
        ->fillForm([
            'parent_id' => $sibling->id,
            'slug' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $root->refresh();
    expect($root->parent_id)->toBe($sibling->id)
        ->and($root->slug)->toBe('root');
});
