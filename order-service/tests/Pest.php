<?php

declare(strict_types=1);

use Ecommerce\PlatformKit\Token\AccessTokenSigner;
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
    ->group('pgsql')
    ->in('Integration');

function orderJwt(int $userId = 1, string $role = 'customer', ?string $email = null): string
{
    $pem = (string) file_get_contents(dirname(__DIR__, 2).'/packages/platform-kit/tests/fixtures/access-private.pem');

    return (new AccessTokenSigner($pem, 'test-1', 'http://auth-app', 'ecommerce'))
        ->issue($userId, [$role]);
}

function mockRedis(): void
{
    Redis::shouldReceive('setex')->zeroOrMoreTimes()->andReturnTrue();
    Redis::shouldReceive('get')->zeroOrMoreTimes()->andReturnNull();
    Redis::shouldReceive('set')->zeroOrMoreTimes()->andReturnTrue();
    Redis::shouldReceive('del')->zeroOrMoreTimes()->andReturn(1);
}
