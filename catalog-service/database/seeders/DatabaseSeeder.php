<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Создаем основной магазин
        $store = Store::firstOrCreate(
            ['slug' => 'tech-universe'],
            ['name' => 'Tech Universe Store', 'is_active' => true]
        );

        // 2. Дерево категорий
        $electronics = Category::create([
            'store_id' => $store->id,
            'name'     => 'Электроника',
            'slug'     => 'electronics',
            'position' => 1,
        ]);

        $smartphones = Category::create([
            'store_id'  => $store->id,
            'parent_id' => $electronics->id,
            'name'      => 'Смартфоны',
            'slug'      => 'smartphones',
            'position'  => 1,
        ]);

        $laptops = Category::create([
            'store_id'  => $store->id,
            'parent_id' => $electronics->id,
            'name'      => 'Ноутбуки',
            'slug'      => 'laptops',
            'position'  => 2,
        ]);

        // 3. Создаем товары с JSONB атрибутами и остатками
        $products = [
            [
                'name'        => 'Apple iPhone 15 Pro',
                'sku'         => 'APL-IP15P-256',
                'category_id' => $smartphones->id,
                'price'       => 1199.99,
                'stock'       => 25,
                'attributes'  => [
                    'brand'   => 'Apple',
                    'color'   => 'Natural Titanium',
                    'storage' => '256GB',
                    'ram'     => '8GB',
                    'screen'  => '6.1 OLED',
                ],
            ],
            [
                'name'        => 'Samsung Galaxy S24 Ultra',
                'sku'         => 'SMS-S24U-512',
                'category_id' => $smartphones->id,
                'price'       => 1299.00,
                'stock'       => 10,
                'attributes'  => [
                    'brand'   => 'Samsung',
                    'color'   => 'Titanium Black',
                    'storage' => '512GB',
                    'ram'     => '12GB',
                    'stylus'  => true,
                ],
            ],
            [
                'name'        => 'Apple MacBook Pro 16 M3 Max',
                'sku'         => 'APL-MBP16-M3M',
                'category_id' => $laptops->id,
                'price'       => 3499.50,
                'stock'       => 4,
                'attributes'  => [
                    'brand'   => 'Apple',
                    'cpu'     => 'Apple M3 Max',
                    'ram'     => '36GB',
                    'ssd'     => '1TB',
                ],
            ],
        ];

        foreach ($products as $item) {
            $product = Product::create([
                'store_id'    => $store->id,
                'category_id' => $item['category_id'],
                'sku'         => $item['sku'],
                'name'        => $item['name'],
                'slug'        => Str::slug($item['name']),
                'description' => "Премиальное устройство {$item['name']}.",
                'price'       => $item['price'],
                'is_active'   => true,
                'attributes'  => $item['attributes'],
            ]);

            // Привязываем остатки
            Stock::create([
                'store_id'          => $store->id,
                'product_id'        => $product->id,
                'quantity'          => $item['stock'],
                'reserved_quantity' => 0,
            ]);
        }
    }
}