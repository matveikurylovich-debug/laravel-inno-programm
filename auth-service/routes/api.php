<?php

use App\Enums\RoleName;
use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\JwksController;
use App\Http\Controllers\Api\GatewayController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('internal/jwks', JwksController::class);
Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::post('refresh', [AuthController::class, 'refresh']);
Route::post('logout', [AuthController::class, 'logout']);
Route::get('profile/email/confirm', [ProfileController::class, 'confirmEmail'])->name('email-change.confirm')->middleware('signed');
Route::get('profile/email/cancel', [ProfileController::class, 'cancelEmailChange'])->name('email-change.cancel')->middleware('signed');
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('two-factor/verify', [AuthController::class, 'verifyTwoFactor']);
Route::post('two-factor/resend', [AuthController::class, 'resendTwoFactor']);

// Поиск публичный: витрина ходит сюда без JWT.
Route::match(['get', 'head'], 'search', [GatewayController::class, 'search']);

// Health-check рекомендаций (доступен для мониторинга без токена)
Route::match(['get', 'head'], 'recommendations/health', [GatewayController::class, 'recommendations'])
    ->defaults('path', 'health');

// Витрина каталога публичная. Запись в каталог — только admin.
Route::match(['get', 'head'], 'catalog/{path?}', [GatewayController::class, 'catalog'])
    ->where('path', '.*');

Route::match(['post', 'put', 'patch', 'delete'], 'catalog/{path?}', [GatewayController::class, 'catalog'])
    ->middleware(['auth:api', 'role:'.RoleName::Admin->value])
    ->where('path', '.*');

// Заказы: любой пользователь с access-токеном. Роль владельца проверяет order-service.
Route::middleware('auth:api')->group(function () {
    Route::any('orders/{path?}', [GatewayController::class, 'orders'])
        ->where('path', '.*');

    Route::any('recommendations/{path?}', [GatewayController::class, 'recommendations'])
        ->where('path', '.*');

    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout-all', [AuthController::class, 'logoutAll']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::post('profile/email', [ProfileController::class, 'requestEmailChange']);
    Route::put('profile/password', [ProfileController::class, 'updatePassword']);
    Route::delete('profile', [ProfileController::class, 'destroy']);

    Route::middleware('role:'.RoleName::Admin->value.','.RoleName::Analyst->value)->group(function () {
        Route::get('admin/users', [AdminUserController::class, 'index']);
        Route::match(['get', 'head'], 'analytics/{path?}', [GatewayController::class, 'analytics'])
            ->where('path', '.*');
    });

    Route::middleware('role:'.RoleName::Admin->value)->group(function () {
        Route::patch('admin/users/{user}/role', [AdminUserController::class, 'updateRole']);
        Route::match(['get', 'head'], 'notifications/{path?}', [GatewayController::class, 'notifications'])
            ->where('path', '.*');
        Route::match(['post', 'put', 'patch', 'delete'], 'notifications/{path?}', [GatewayController::class, 'notifications'])
            ->where('path', '.*');
    });
});

Route::get('catalog-admin/access', fn () => response()->noContent())->middleware(['auth:api', 'role:'.RoleName::Admin->value]);
