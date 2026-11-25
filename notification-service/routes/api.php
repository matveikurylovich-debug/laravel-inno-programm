<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:api', 'can:notifications.read'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/{id}', [NotificationController::class, 'show']);
});

Route::post('/notifications/{id}/replay', [NotificationController::class, 'replay'])
    ->middleware(['auth:api', 'can:notifications.replay']);
