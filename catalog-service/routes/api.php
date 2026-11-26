<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProductImageController;
use App\Http\Controllers\Api\V1\StoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(static function (): void {
    Route::prefix('stores/{storeId}')->whereNumber('storeId')->group(static function (): void {
        Route::get('categories/tree', [CategoryController::class, 'tree'])->name('stores.categories.tree');
        Route::get('products', [ProductController::class, 'index'])->name('stores.products.index');
    });

    Route::get('products/{id}', [ProductController::class, 'show'])->whereNumber('id')->name('products.show');
    Route::get('stores', [StoreController::class, 'index'])->name('stores.index');

    Route::middleware(['auth:api', 'can:catalog.write'])->group(static function (): void {
        Route::post('stores', [StoreController::class, 'store'])->name('stores.store');
        Route::match(['put', 'patch'], 'stores/{store}', [StoreController::class, 'update'])
            ->whereNumber('store')
            ->name('stores.update');
        Route::delete('stores/{store}', [StoreController::class, 'destroy'])
            ->whereNumber('store')
            ->name('stores.destroy');

        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
        Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update'])
            ->whereNumber('category')
            ->name('categories.update');
        Route::delete('categories/{category}', [CategoryController::class, 'destroy'])
            ->whereNumber('category')
            ->name('categories.destroy');

        Route::post('products', [ProductController::class, 'store'])->name('products.store');
        Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update'])
            ->whereNumber('product')
            ->name('products.update');
        Route::delete('products/{product}', [ProductController::class, 'destroy'])
            ->whereNumber('product')
            ->name('products.destroy');
        Route::match(['put', 'patch'], 'products/{product}/stock', [ProductController::class, 'updateStock'])
            ->whereNumber('product')
            ->name('products.stock.update');

        Route::post('products/{product}/images', [ProductImageController::class, 'store'])
            ->whereNumber('product')
            ->name('products.images.store');
        Route::delete('images/{image}', [ProductImageController::class, 'destroy'])
            ->whereNumber('image')
            ->name('images.destroy');
    });
});
