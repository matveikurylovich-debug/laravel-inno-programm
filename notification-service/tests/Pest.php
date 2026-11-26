<?php

declare(strict_types=1);

use App\Contracts\NotificationLogRepository;
use App\Models\NotificationLog;
use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use Tests\HttpTestCase;
use Tests\TestCase;

uses(TestCase::class)->in('Feature');
uses(HttpTestCase::class)->in('Unit/Mail');

/**
 * @param  list<string>  $roles
 */
function issueJwtToken(array $roles = ['admin'], int $userId = 1, int $ttl = 900, string $type = 'access'): string
{
    $pem = (string) file_get_contents(base_path('../packages/platform-kit/tests/fixtures/access-private.pem'));
    $now = time();

    return new AccessTokenSigner($pem, 'test-1', 'http://auth-app', 'ecommerce')
        ->issue($userId, $roles, null, [
            'type' => $type,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
        ]);
}

/**
 * @return array{Authorization: string}
 */
function roleHeaders(string $role, int $userId = 1): array
{
    return ['Authorization' => 'Bearer '.issueJwtToken([$role], $userId)];
}

/**
 * @param  array<string, mixed>  $attributes
 */
function seedNotificationLog(array $attributes): NotificationLog
{
    if (TestCase::$active instanceof TestCase) {
        return TestCase::$active->seedLog($attributes);
    }

    return resolve(NotificationLogRepository::class)->createPending($attributes);
}

/**
 * @return list<NotificationLog>
 */
function allNotificationLogs(): array
{
    if (TestCase::$active instanceof TestCase) {
        return TestCase::$active->allLogs();
    }

    return [];
}

function findNotificationLog(string $id): NotificationLog
{
    if (TestCase::$active instanceof TestCase) {
        return TestCase::$active->findLog($id);
    }

    return resolve(NotificationLogRepository::class)->findOrFail($id);
}
