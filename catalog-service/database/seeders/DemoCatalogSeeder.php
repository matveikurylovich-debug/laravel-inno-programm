<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Seeder;

class DemoCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoStoresSeeder::class,
            DemoCategoriesSeeder::class,
            DemoProductsSeeder::class,
        ]);

        $this->command?->info(sprintf(
            'Каталог: магазинов %d, категорий %d, товаров %d.',
            Store::query()->count(),
            Category::query()->count(),
            Product::query()->count(),
        ));
    }
}
