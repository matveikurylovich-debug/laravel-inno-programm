<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\OrderController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api'])->prefix('orders')->name('api.v1.orders.')->group(function (): void {
    Route::get('/', [OrderController::class, 'index'])->name('index');
    Route::post('/', [OrderController::class, 'store'])->middleware('idempotency')->name('store');
    Route::get('/{order}', [OrderController::class, 'show'])->whereUuid('order')->name('show');
    Route::post('/{order}/cancel', [OrderController::class, 'cancel'])->whereUuid('order')->name('cancel');
});
