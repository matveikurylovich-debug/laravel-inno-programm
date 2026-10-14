<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_storefront_of_one_store_does_not_show_another_stores_products(): void
    {
        $first = $this->store('one');
        $second = $this->store('two');
        $this->product($first, 'Phone');
        $this->product($second, 'Tablet');

        $this->getJson("/api/v1/stores/{$first->id}/products")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Phone');

        $this->getJson('/api/v1/stores')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_store_admin_cannot_see_or_change_another_store(): void
    {
        $own = $this->store('own');
        $foreign = $this->store('foreign');
        $ownProduct = $this->product($own, 'Own');
        $foreignProduct = $this->product($foreign, 'Foreign');

        $this->withToken($this->token($own->id))
            ->getJson('/api/v1/stores')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        $this->withToken($this->token($own->id))
            ->getJson("/api/v1/stores/{$foreign->id}/products")
            ->assertForbidden();

        $this->withToken($this->token($own->id))
            ->patchJson("/api/v1/products/{$foreignProduct->id}", ['name' => 'Stolen'])
            ->assertNotFound();

        $this->withToken($this->token($own->id))
            ->patchJson("/api/v1/products/{$ownProduct->id}", ['name' => 'Renamed'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed');

        $this->withToken($this->token($own->id))
            ->postJson('/api/v1/stores', ['name' => 'Extra'])
            ->assertForbidden();
    }

    public function test_platform_admin_still_manages_every_store(): void
    {
        $first = $this->store('first');
        $second = $this->store('second');
        $category = Category::query()->create([
            'store_id' => $second->id,
            'name' => 'Phones',
            'slug' => 'phones',
        ]);

        $this->withToken($this->token())
            ->getJson('/api/v1/stores')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->withToken($this->token())
            ->postJson('/api/v1/products', [
                'store_id' => $second->id,
                'category_id' => $category->id,
                'name' => 'Tablet',
                'price' => 20,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.store_id', $second->id);

        $this->assertSame(1, Product::query()->where('store_id', $second->id)->count());
        $this->assertSame(0, Product::query()->where('store_id', $first->id)->count());
    }

    private function store(string $slug): Store
    {
        return Store::query()->create([
            'name' => $slug,
            'slug' => $slug,
            'is_active' => true,
        ]);
    }

    private function product(Store $store, string $name): Product
    {
        return Product::query()->create([
            'store_id' => $store->id,
            'name' => $name,
            'slug' => strtolower($name).'-'.$store->id,
            'sku' => strtoupper($name).'-'.$store->id,
            'price' => 10,
            'is_active' => true,
        ]);
    }

    private function token(?int $storeId = null): string
    {
        return JWT::encode([
            'sub' => 1,
            'role' => 'admin',
            'store_id' => $storeId,
            'type' => 'access',
            'exp' => time() + 3600,
        ], (string) config('jwt.secret'), 'HS256');
    }
}
