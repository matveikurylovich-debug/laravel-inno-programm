<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('rejects unauthenticated, forged, expired, or refresh tokens with 401', function (): void {
    getJson('/api/v1/notifications')->assertUnauthorized();
    postJson('/api/v1/notifications/1/replay')->assertUnauthorized();

    $forged = JWT::encode([
        'iss' => 'http://auth-app',
        'aud' => 'ecommerce',
        'sub' => 1,
        'roles' => ['admin'],
        'type' => 'access',
        'jti' => 'wrong-key',
        'iat' => time(),
        'nbf' => time(),
        'exp' => time() + 900,
    ], 'wrong-secret-key-that-is-at-least-32-bytes-long', 'HS256');

    getJson('/api/v1/notifications', ['Authorization' => 'Bearer '.$forged])
        ->assertUnauthorized();

    getJson('/api/v1/notifications', ['Authorization' => 'Bearer '.issueJwtToken(ttl: -60)])
        ->assertUnauthorized();

    getJson('/api/v1/notifications', ['Authorization' => 'Bearer '.issueJwtToken(type: 'refresh')])
        ->assertUnauthorized();
});

it('protects notification routes with auth:api and permission gates', function (): void {
    $routes = collect(Route::getRoutes()->getRoutes())->filter(
        static fn ($route): bool => str_starts_with($route->uri(), 'api/v1/notifications'),
    );

    expect($routes)->not->toBeEmpty();

    foreach ($routes as $route) {
        $middleware = $route->gatherMiddleware();

        expect($middleware)->toContain('auth:api');
        $expected = str_contains($route->uri(), 'replay') ? 'can:notifications.replay' : 'can:notifications.read';
        expect($middleware)->toContain($expected);
    }
});

it('enforces case-insensitive role permissions across admin, analyst, and customer', function (): void {
    getJson('/api/v1/notifications', ['Authorization' => 'Bearer '.issueJwtToken(['Admin'])])
        ->assertOk();

    getJson('/api/v1/notifications', roleHeaders('analyst'))
        ->assertOk();

    getJson('/api/v1/notifications', roleHeaders('customer'))
        ->assertForbidden();

    postJson('/api/v1/notifications/1/replay', [], roleHeaders('analyst'))
        ->assertForbidden();
});
