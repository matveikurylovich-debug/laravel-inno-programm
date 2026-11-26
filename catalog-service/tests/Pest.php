<?php

declare(strict_types=1);

use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->group('pgsql')
    ->in('Integration');

function catalogJwt(string $role = 'admin', int $userId = 1): string
{
    $pem = (string) file_get_contents(dirname(__DIR__, 2).'/packages/platform-kit/tests/fixtures/access-private.pem');

    return new AccessTokenSigner($pem, 'test-1', 'http://auth-app', 'ecommerce')
        ->issue($userId, [$role]);
}
