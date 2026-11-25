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
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $secret = getenv('JWT_ACCESS_SECRET');
        $command = $_SERVER['argv'][1] ?? '';
        $tooling = ['package:discover', 'config:clear', 'config:cache', 'key:generate', 'about', 'list', 'test'];
        $argv0 = $_SERVER['argv'][0] ?? '';
        if (($secret === false || $secret === '') && ! in_array($command, $tooling, true) && ! str_contains($argv0, 'phpstan')) {
            throw new RuntimeException('JWT-секрет пуст, запустите ./start.sh');
        }

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
