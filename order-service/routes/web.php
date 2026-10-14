<?php

declare(strict_types=1);

use App\Http\Controllers\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
