<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\StartBrowserSession;
use FilesystemIterator;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

uses(TestCase::class);

arch('application code uses strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('tests use strict types')
    ->expect('Tests')
    ->toUseStrictTypes();

arch('api controllers are versioned')
    ->expect('App\Http\Controllers\Api\V1')
    ->toHaveSuffix('Controller');

arch('middleware has no Middleware suffix')
    ->expect('App\Http\Middleware')
    ->not->toHaveSuffix('Middleware');

arch('listeners do not name the broker')
    ->expect('App\Listeners')
    ->not->toHavePrefix('SendKafka');

arch('producers are named for their topic')
    ->expect('App\Messaging\Producers')
    ->toHaveSuffix('Producer');

arch('messages are named for their event')
    ->expect('App\Messaging\Messages')
    ->toHaveSuffix('Message');

arch('contracts omit the Interface suffix')
    ->expect('App\Contracts')
    ->not->toHaveSuffix('Interface');

it('keeps forbidden root directories out of the application', function (): void {
    foreach (['Actions', 'Dto', 'DTO', 'DTOs', 'Auth', 'Kafka', 'Infrastructure', 'Search'] as $directory) {
        expect(is_dir(app_path($directory)))->toBeFalse();
    }

    expect(is_dir(app_path('Services/Kafka')))->toBeFalse();
    expect(is_dir(app_path('Services/Analytics')))->toBeFalse();
    expect(glob(app_path('Services/*.php')) ?: [])->toBe([]);
    expect(glob(app_path('Http/Controllers/Api/*.php')) ?: [])->toBe([]);
    expect(is_file(app_path('Http/Middleware/JwtMiddleware.php')))->toBeFalse();
    expect(class_exists(StartBrowserSession::class))->toBeTrue();
    expect(class_exists(EnsureRole::class))->toBeTrue();
});

it('names middleware with a layout prefix', function (): void {
    $files = glob(app_path('Http/Middleware/*.php')) ?: [];

    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $name = basename($file, '.php');

        expect($name)->toMatch('/^(Ensure|Authenticate|Resolve|Start)/');
        expect($name)->not->toEndWith('Middleware');
    }
});

it('places form requests under versioned api resources', function (): void {
    $root = app_path('Http/Requests');
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    $found = 0;

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $found++;
        $relative = substr($file->getPathname(), strlen($root) + 1);

        expect(str_replace('\\', '/', $relative))->toMatch('/^Api\/V1\/(Auth|Profile|Admin)\//');
    }

    expect($found)->toBeGreaterThan(0);
});

it('declares strict types in every application and test file', function (): void {
    $directories = [app_path(), dirname(__DIR__)];

    foreach ($directories as $directory) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            expect(file_get_contents($file->getPathname()))->toContain('declare(strict_types=1);');
        }
    }
});

it('names every api route api.v1.*', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(function (Illuminate\Routing\Route $route): bool {
        $action = $route->getActionName();

        return str_starts_with($action, 'App\\')
            || ($action === 'Closure' && str_starts_with($route->uri(), 'api'));
    });

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        expect($route->uri())->toStartWith('api/v1');
        expect($route->getName())->toStartWith('api.v1.');
    }
});
