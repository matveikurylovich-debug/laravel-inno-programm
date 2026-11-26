<?php

declare(strict_types=1);

use App\Http\Middleware\AuthenticateCatalogAdmin;
use Ecommerce\PlatformKit\Laravel\ProblemJson;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['__Host-access', 'access_token', 'refresh_token']);
        $middleware->web(append: [AuthenticateCatalogAdmin::class]);
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        ProblemJson::register($exceptions);
    })->create();
