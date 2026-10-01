<?php

use App\Http\Controllers\Api\AdminUserController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login']);
Route::post('register', [AuthController::class, 'register']);
Route::post('refresh', [AuthController::class, 'refresh']);
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);
Route::post('two-factor/verify', [AuthController::class, 'verifyTwoFactor']);
Route::post('two-factor/resend', [AuthController::class, 'resendTwoFactor']);

Route::middleware('jwt.auth')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::patch('profile', [ProfileController::class, 'update']);
    Route::put('profile/password', [ProfileController::class, 'updatePassword']);
    Route::delete('profile', [ProfileController::class, 'destroy']);

    Route::middleware('role:admin,analyst')->group(function () {
        Route::get('admin/users', [AdminUserController::class, 'index']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::patch('admin/users/{user}/role', [AdminUserController::class, 'updateRole']);
    });
});
