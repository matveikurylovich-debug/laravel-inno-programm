<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Http\Controllers\Api\V1\AdminUserController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\GatewayController;
use App\Http\Controllers\Api\V1\JwksController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('internal/jwks', JwksController::class)->name('jwks');
    Route::post('login', [AuthController::class, 'login'])->name('login')->middleware('throttle:login');
    Route::post('register', [AuthController::class, 'register'])->name('register')->middleware('throttle:register');
    Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('profile/email/confirm', [ProfileController::class, 'confirmEmail'])->name('email-change.confirm')->middleware('signed');
    Route::get('profile/email/cancel', [ProfileController::class, 'cancelEmailChange'])->name('email-change.cancel')->middleware('signed');
    Route::post('forgot-password', [AuthController::class, 'forgotPassword'])->name('password.email')->middleware('throttle:password-reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.reset')->middleware('throttle:password-reset');
    Route::post('two-factor/verify', [AuthController::class, 'verifyTwoFactor'])->name('two-factor.verify')->middleware('throttle:two-factor');
    Route::post('two-factor/resend', [AuthController::class, 'resendTwoFactor'])->name('two-factor.resend')->middleware('throttle:two-factor');
    Route::get('gateway/verify', [GatewayController::class, 'verify'])->name('gateway.verify')->middleware('auth:api');

    Route::middleware('auth:api')->group(function (): void {
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
        Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::post('profile/email', [ProfileController::class, 'requestEmailChange'])->name('profile.email');
        Route::put('profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');
        Route::delete('profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        Route::middleware('role:'.RoleName::Admin->value.','.RoleName::Analyst->value)->group(function (): void {
            Route::get('admin/users', [AdminUserController::class, 'index'])->name('admin.users.index');
            Route::get('admin/roles', [AdminUserController::class, 'roles'])->name('admin.roles.index');
        });

        Route::middleware('role:'.RoleName::Admin->value)->group(function (): void {
            Route::patch('admin/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('admin.users.role');
        });
    });

    Route::get('catalog-admin/access', fn (): Response => response()->noContent())
        ->name('catalog-admin.access')
        ->middleware(['auth:api', 'role:'.RoleName::Admin->value]);
});
