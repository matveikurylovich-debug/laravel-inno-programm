<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::middleware('jwt.auth')->group(function () {
    Route::middleware('role:admin,analyst')->group(function () {
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/{id}', [NotificationController::class, 'show']);
    });

    Route::post('/notifications/{id}/replay', [NotificationController::class, 'replay'])
        ->middleware('role:admin');
});
