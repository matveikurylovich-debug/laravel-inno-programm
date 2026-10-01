<?php

use App\Http\Controllers\Api\NotificationController;
use Illuminate\Support\Facades\Route;

Route::prefix('notifications')
    ->middleware(['jwt.auth', 'role:admin,analyst'])
    ->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/{id}/replay', [NotificationController::class, 'replay']);
        Route::get('/{id}', [NotificationController::class, 'show']);
    });
