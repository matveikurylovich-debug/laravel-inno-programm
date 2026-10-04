<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Junges\Kafka\Facades\Kafka;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_lists_store_tree_and_product(): void
    {
        [$store, $product] = $this->catalog();

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
    }

    public function test_admin_routes_require_admin_token(): void
    {
        $this->postJson('/api/v1/stores', ['name' => 'New'])
            ->assertUnauthorized();

        $this->withToken($this->token('customer'))
            ->postJson('/api/v1/stores', ['name' => 'New'])
            ->assertForbidden();
    }

    public function test_admin_creates_store_category_and_product(): void
    {
        $store = Store::query()->create([
            'name' => 'Shop',
            'slug' => 'shop',
            'is_active' => true,
        ]);
        $category = Category::query()->create([
            'store_id' => $store->id,
            'name' => 'Phones',
            'slug' => 'phones',
        ]);

        $this->withToken($this->token('admin'))
            ->postJson('/api/v1/products', [
                'store_id' => $store->id,
                'category_id' => $category->id,
                'name' => 'Tablet',
                'price' => 20,
                'quantity' => 3,
                'attributes' => ['brand' => ['nested']],
            ])
            ->assertUnprocessable();

        $this->withToken($this->token('admin'))
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

        $this->assertSame(3, Stock::query()->where('product_id', Product::query()->where('slug', 'tablet')->value('id'))->value('quantity'));
        Kafka::assertPublishedOn('catalog.events');
    }

    public function test_category_parent_must_belong_to_the_same_store(): void
    {
        $first = Store::query()->create(['name' => 'A', 'slug' => 'a', 'is_active' => true]);
        $second = Store::query()->create(['name' => 'B', 'slug' => 'b', 'is_active' => true]);
        $parent = Category::query()->create([
            'store_id' => $second->id,
            'name' => 'Other',
            'slug' => 'other',
        ]);

        $this->withToken($this->token('admin'))
            ->postJson('/api/v1/categories', [
                'store_id' => $first->id,
                'parent_id' => $parent->id,
                'name' => 'Child',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    }

    public function test_admin_updates_stock_and_uploads_image(): void
    {
        [, $product] = $this->catalog();
        Storage::fake('minio');

        $this->withToken($this->token('admin'))
            ->patchJson("/api/v1/products/{$product->id}/stock", ['quantity' => 7])
            ->assertOk()
            ->assertJsonPath('data.quantity', 7);

        $this->withToken($this->token('admin'))
            ->post("/api/v1/products/{$product->id}/images", [
                'image' => UploadedFile::fake()->createWithContent(
                    'phone.jpg',
                    base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA='),
                ),
                'is_primary' => true,
            ])
            ->assertCreated();

        $this->assertNotEmpty(Storage::disk('minio')->allFiles('products/'.$product->id));
    }

    /**
     * @return array{0: Store, 1: Product}
     */
    private function catalog(): array
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
        Stock::query()->create([
            'store_id' => $store->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        return [$store, $product];
    }

    private function token(string $role): string
    {
        return JWT::encode([
            'sub' => 1,
            'role' => $role,
            'type' => 'access',
            'exp' => time() + 3600,
        ], (string) config('jwt.secret'), 'HS256');
    }
}
