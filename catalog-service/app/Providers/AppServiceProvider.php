<?php

namespace App\Providers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Observers\CategoryObserver;
use App\Observers\ProductObserver;
use App\Observers\StockObserver;
use App\Support\CurrentStore;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(CurrentStore::class);
    }

    public function boot(): void
    {
        // Подключаем наблюдателей
        Category::observe(CategoryObserver::class);
        Product::observe(ProductObserver::class);
        Stock::observe(StockObserver::class);
    }
}
