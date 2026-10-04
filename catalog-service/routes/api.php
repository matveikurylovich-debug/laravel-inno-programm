<?php

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductImageController;
use App\Http\Controllers\Api\StoreController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // 1. Публичные маршруты (Витрина)
    Route::prefix('stores/{storeId}')->group(function () {
        Route::get('categories/tree', [CategoryController::class, 'tree']);
        Route::get('products', [ProductController::class, 'index']);
    });

    Route::get('products/{id}', [ProductController::class, 'show']);
    Route::get('stores', [StoreController::class, 'index']);

    // 2. Защищенные маршруты админки (только роль admin)
    Route::middleware(['jwt.auth:admin'])->group(function () {
        // Магазины
        Route::post('stores', [StoreController::class, 'store']);
        Route::match(['put', 'patch'], 'stores/{store}', [StoreController::class, 'update']);
        Route::delete('stores/{store}', [StoreController::class, 'destroy']);

        // Категории
        Route::post('categories', [CategoryController::class, 'store']);
        Route::match(['put', 'patch'], 'categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        // Товары
        Route::post('products', [ProductController::class, 'store']);
        Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
        Route::match(['put', 'patch'], 'products/{product}/stock', [ProductController::class, 'updateStock']);

        // Изображения (MinIO)
        Route::post('products/{product}/images', [ProductImageController::class, 'store']);
        Route::delete('images/{image}', [ProductImageController::class, 'destroy']);
    });
});
