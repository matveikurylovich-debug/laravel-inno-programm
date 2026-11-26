<?php

declare(strict_types=1);

use App\Http\Middleware\StartBrowserSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

it('sets HttpOnly secure cookies and removes browser tokens from JSON', function (): void {
    $request = Request::create('/api/v1/two-factor/verify', 'POST', server: [
        'HTTP_X_BROWSER_SESSION' => '1', 'HTTP_ORIGIN' => 'http://localhost:3000',
    ]);
    $response = resolve(StartBrowserSession::class)->handle($request, fn () => response()->json([
        'access_token' => 'access', 'refresh_token' => 'refresh', 'user' => ['id' => 1],
    ]));
    expect($response)->toBeInstanceOf(JsonResponse::class);
    if (! $response instanceof JsonResponse) {
        return;
    }
    expect($response->getData(true))->not->toHaveKeys(['access_token', 'refresh_token']);
    foreach ($response->headers->getCookies() as $cookie) {
        expect($cookie->isHttpOnly())->toBeTrue();
        expect($cookie->isSecure())->toBeTrue();
    }
    $cookies = collect($response->headers->getCookies())->keyBy(fn ($cookie) => $cookie->getName());
    expect($cookies->get('__Host-access')?->getPath())->toBe('/');
    expect($cookies->get('refresh_token')?->getPath())->toBe('/api/auth');
    expect($response->headers->getCookies())->toHaveCount(2);
});

it('rejects a cross origin cookie mutation with 403', function (): void {
    $request = Request::create('/api/v1/orders', 'POST', cookies: ['__Host-access' => 'access'], server: ['HTTP_ORIGIN' => 'https://evil.example']);
    $response = resolve(StartBrowserSession::class)->handle($request, fn (): never => throw new RuntimeException('must not run'));
    expect($response->getStatusCode())->toBe(403);
});

it('accepts access and refresh cookies without exposing them to browser code', function (): void {
    $request = Request::create('/api/v1/refresh', 'POST', cookies: ['refresh_token' => 'refresh'], server: ['HTTP_ORIGIN' => 'http://localhost:3000']);
    resolve(StartBrowserSession::class)->handle($request, function ($request) {
        expect($request->input('refresh_token'))->toBe('refresh');

        return response()->json(['ok' => true]);
    });
});
