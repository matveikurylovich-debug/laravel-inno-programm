<?php

declare(strict_types=1);

use App\Http\Middleware\IdempotencyMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

$store = [];

beforeEach(function () use (&$store) {
    $store = [];

    Redis::shouldReceive('get')
        ->andReturnUsing(function (string $key) use (&$store) {
            return $store[$key] ?? null;
        });

    Redis::shouldReceive('set')
        ->andReturnUsing(function (string $key, mixed $value, ...$args) use (&$store): bool {
            if (in_array('NX', $args, true) && array_key_exists($key, $store)) {
                return false;
            }

            $store[$key] = $value;

            return true;
        });

    Redis::shouldReceive('setex')
        ->andReturnUsing(function (string $key, int $ttl, mixed $value) use (&$store): bool {
            $store[$key] = $value;

            return true;
        });

    Redis::shouldReceive('del')
        ->andReturnUsing(function (string $key) use (&$store): int {
            unset($store[$key]);

            return 1;
        });
});

it('replays the cached response for a second request with the same key', function () {
    $middleware = new IdempotencyMiddleware;
    $calls = 0;

    $first = $middleware->handle(idempotencyRequest('same-key'), function () use (&$calls) {
        $calls++;

        return response()->json(['created' => true], 201);
    });

    $second = $middleware->handle(idempotencyRequest('same-key'), function () use (&$calls) {
        $calls++;

        return response()->json(['created' => false], 201);
    });

    expect($calls)->toBe(1)
        ->and($first->getStatusCode())->toBe(201)
        ->and($second->getStatusCode())->toBe(201)
        ->and($second->headers->get('X-Idempotent-Replay'))->toBe('true')
        ->and($second->getContent())->toBe('{"created":true}');
});

function idempotencyRequest(string $key): Request
{
    $request = Request::create('/api/orders', 'POST');
    $request->headers->set('Idempotency-Key', $key);
    $request->attributes->set('auth_user_id', 7);

    return $request;
}
