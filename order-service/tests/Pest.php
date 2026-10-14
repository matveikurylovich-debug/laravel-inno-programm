<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\PostgresPersistenceTestCase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(PostgresPersistenceTestCase::class)
    ->in('Integration');

function orderJwt(int $userId = 1, string $role = 'customer', ?string $email = null): string
{
    return JWT::encode([
        'sub' => $userId,
        'email' => $email ?? "user{$userId}@example.com",
        'role' => $role,
        'type' => 'access',
        'exp' => time() + 3600,
    ], (string) config('jwt.access_secret'), 'HS256');
}

function mockRedis(): void
{
    Redis::shouldReceive('setex')->zeroOrMoreTimes()->andReturnTrue();
    Redis::shouldReceive('get')->zeroOrMoreTimes()->andReturnNull();
    Redis::shouldReceive('set')->zeroOrMoreTimes()->andReturnTrue();
    Redis::shouldReceive('del')->zeroOrMoreTimes()->andReturn(1);
}
