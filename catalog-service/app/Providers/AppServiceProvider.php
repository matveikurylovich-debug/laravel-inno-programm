<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\AuthenticateCatalogAdmin;
use App\Models\Category;
use App\Models\Product;
use App\Models\Stock;
use App\Models\Store;
use App\Observers\CategoryObserver;
use App\Observers\ProductObserver;
use App\Observers\StockObserver;
use App\Observers\StoreObserver;
use Ecommerce\PlatformKit\Laravel\RevocationInspector;
use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AuthenticateCatalogAdmin::class,
            static fn (Application $app): AuthenticateCatalogAdmin => new AuthenticateCatalogAdmin(
                static fn (): AccessTokenVerifier => $app->make(AccessTokenVerifier::class),
                $app->make(RevocationInspector::class),
            ),
        );
    }

    public function boot(): void
    {
        Category::observe(CategoryObserver::class);
        Product::observe(ProductObserver::class);
        Stock::observe(StockObserver::class);
        Store::observe(StoreObserver::class);
    }
}
