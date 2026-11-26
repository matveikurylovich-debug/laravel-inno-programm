<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::middleware(['auth:api', 'can:notifications.read'])->group(function (): void {
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/{id}', [NotificationController::class, 'show'])->name('notifications.show');
    });

    Route::post('/notifications/{id}/replay', [NotificationController::class, 'replay'])
        ->middleware(['auth:api', 'can:notifications.replay'])
        ->name('notifications.replay');
});
