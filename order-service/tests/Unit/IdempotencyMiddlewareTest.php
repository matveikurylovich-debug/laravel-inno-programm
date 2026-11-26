<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureIdempotentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    Cache::flush();
});

it('rejects order creation without an idempotency key', function (): void {
    $middleware = new EnsureIdempotentRequest;
    $request = Request::create('/api/orders', 'POST', content: '{"store_id":1}');
    $request->attributes->set('auth_user_id', 7);

    $response = $middleware->handle($request, fn () => response()->json(['created' => true], 201));

    expect($response->getStatusCode())->toBe(400)
        ->and($response->getContent())->toContain('Idempotency-Key');
});

it('replays the cached response for a second request with the same key', function (): void {
    $middleware = new EnsureIdempotentRequest;
    $calls = 0;

    $first = $middleware->handle(idempotencyRequest('same-key', '{"a":1}'), function () use (&$calls) {
        $calls++;

        return response()->json(['created' => true], 201);
    });

    $second = $middleware->handle(idempotencyRequest('same-key', '{"a":1}'), function () use (&$calls) {
        $calls++;

        return response()->json(['created' => false], 201);
    });

    expect($calls)->toBe(1)
        ->and($first->getStatusCode())->toBe(201)
        ->and($second->getStatusCode())->toBe(201)
        ->and($second->headers->get('X-Idempotent-Replay'))->toBe('true')
        ->and($second->getContent())->toBe('{"created":true}');
});

it('rejects the same idempotency key when the body changes', function (): void {
    $middleware = new EnsureIdempotentRequest;
    $middleware->handle(idempotencyRequest('same-key', '{"a":1}'), fn () => response()->json(['created' => true], 201));

    $response = $middleware->handle(idempotencyRequest('same-key', '{"a":2}'), fn () => response()->json(['created' => false], 201));

    expect($response->getStatusCode())->toBe(422);
});

function idempotencyRequest(string $key, string $body): Request
{
    $request = Request::create('/api/orders', 'POST', content: $body);
    $request->headers->set('Idempotency-Key', $key);
    $request->headers->set('Content-Type', 'application/json');
    $request->attributes->set('auth_user_id', 7);

    return $request;
}
