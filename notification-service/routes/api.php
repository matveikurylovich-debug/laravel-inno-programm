<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

// Роли admin/analyst проверяет auth-service на gateway. Здесь только access-токен.
Route::middleware('jwt.auth')->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    Route::post('/notifications/{id}/replay', [NotificationController::class, 'replay']);
});
