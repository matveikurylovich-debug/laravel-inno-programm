<?php

use App\Http\Middleware\BrowserSession;
use Illuminate\Http\Request;

it('sets HttpOnly secure cookies and removes browser tokens from JSON', function () {
    $request = Request::create('/api/two-factor/verify', 'POST', server: [
        'HTTP_X_BROWSER_SESSION' => '1', 'HTTP_ORIGIN' => 'http://localhost:3000',
    ]);
    $response = app(BrowserSession::class)->handle($request, fn () => response()->json([
        'access_token' => 'access', 'refresh_token' => 'refresh', 'user' => ['id' => 1],
    ]));
    expect($response->getData(true))->not->toHaveKeys(['access_token', 'refresh_token']);
    foreach ($response->headers->getCookies() as $cookie) {
        expect($cookie->isHttpOnly())->toBeTrue();
        expect($cookie->isSecure())->toBeTrue();
    }
    expect($response->headers->getCookies())->toHaveCount(2);
});

it('rejects a cross origin cookie mutation with 403', function () {
    $request = Request::create('/api/orders', 'POST', cookies: ['access_token' => 'access'], server: ['HTTP_ORIGIN' => 'https://evil.example']);
    $response = app(BrowserSession::class)->handle($request, fn () => throw new RuntimeException('must not run'));
    expect($response->getStatusCode())->toBe(403);
});

it('accepts access and refresh cookies without exposing them to browser code', function () {
    $request = Request::create('/api/refresh', 'POST', cookies: ['refresh_token' => 'refresh'], server: ['HTTP_ORIGIN' => 'http://localhost:3000']);
    app(BrowserSession::class)->handle($request, function ($request) {
        expect($request->input('refresh_token'))->toBe('refresh');

        return response()->json(['ok' => true]);
    });
});
