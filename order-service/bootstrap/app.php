<?php

declare(strict_types=1);

use App\Http\Controllers\Webhooks\StripeWebhookController;
use App\Http\Middleware\EnsureIdempotentRequest;
use Ecommerce\PlatformKit\Laravel\ProblemJson;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            Route::post('/webhooks/stripe', [StripeWebhookController::class, 'handle']);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'idempotency' => EnsureIdempotentRequest::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        ProblemJson::register($exceptions);
    })->create();
