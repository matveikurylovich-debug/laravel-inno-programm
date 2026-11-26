<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Stock;
use App\Models\Store;
use App\Models\StoreLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoProductsSeeder extends Seeder
{
    public function run(): void
    {
        Model::withoutEvents(function (): void {
            foreach (DemoCatalogFixtures::stores() as $storeData) {
                $store = Store::query()->where('slug', $storeData['slug'])->first();

                if (! $store instanceof Store) {
                    throw new ModelNotFoundException("Магазин {$storeData['slug']} не найден. Сначала запустите DemoStoresSeeder.");
                }

                $storedLocations = StoreLocation::query()
                    ->where('store_id', $store->id)
                    ->get()
                    ->keyBy('address');
                $locations = [];

                foreach ($storeData['locations'] as $location) {
                    $stored = $storedLocations->get($location['address']);

                    if (! $stored instanceof StoreLocation) {
                        throw new ModelNotFoundException("Точка {$location['address']} не найдена. Сначала запустите DemoStoresSeeder.");
                    }

                    $locations[] = $stored;
                }

                foreach ($storeData['products'] as $item) {
                    $category = Category::query()
                        ->where('store_id', $store->id)
                        ->where('slug', $item[2])
                        ->first();

                    if (! $category instanceof Category) {
                        throw new ModelNotFoundException("Категория {$item[2]} не найдена. Сначала запустите DemoCategoriesSeeder.");
                    }

                    $this->seedProduct($store, $locations, $category, $item);
                }
            }
        });
    }

    /**
     * @param  list<StoreLocation>  $locations
     * @param  array{0: string, 1: string, 2: string, 3: float|int, 4: int, 5: array<string, mixed>}  $item
     */
    private function seedProduct(Store $store, array $locations, Category $category, array $item): void
    {
        [$sku, $name, , $price, $stock, $attributes] = $item;

        $product = Product::query()->firstOrCreate(
            ['store_id' => $store->id, 'sku' => $sku],
            [
                'category_id' => $category->id,
                'name' => $name,
                'slug' => Str::slug($sku),
                'description' => "{$name}. Тестовый товар для витрины и поиска.",
                'price' => $price,
                'is_active' => true,
                'attributes' => $attributes,
            ],
        );

        foreach ($locations as $index => $location) {
            $quantity = $index === 0 ? (int) $stock : max(0, intdiv((int) $stock, 3));

            Stock::query()->updateOrCreate(
                ['location_id' => $location->id, 'product_id' => $product->id],
                [
                    'store_id' => $store->id,
                    'quantity' => $quantity,
                    'reserved_quantity' => 0,
                ],
            );
        }

        ProductImage::query()->firstOrCreate(
            ['product_id' => $product->id, 'position' => 0],
            [
                'path' => "seed/{$sku}.jpg",
                'url' => 'https://picsum.photos/seed/'.rawurlencode($sku).'/800/800',
                'is_primary' => true,
            ],
        );
    }
}
