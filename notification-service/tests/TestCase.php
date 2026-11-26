<?php

declare(strict_types=1);

namespace Tests;

use App\Contracts\NotificationLogRepository;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\NotificationLog;
use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Junges\Kafka\Facades\Kafka;
use MongoDB\Collection;
use MongoDB\Laravel\Eloquent\Builder as MongoEloquentBuilder;
use Tests\Support\InMemoryNotificationLogRepository;

abstract class TestCase extends BaseTestCase
{
    public static ?self $active = null;

    protected ?InMemoryNotificationLogRepository $memoryLogs = null;

    private bool $realMongo = false;

    protected function setUp(): void
    {
        if (! defined('RD_KAFKA_PARTITION_UA')) {
            define('RD_KAFKA_PARTITION_UA', -1);
        }

        parent::setUp();
        self::$active = $this;
        Kafka::fake();

        $this->realMongo = $this->detectMongo();

        if ($this->realMongo) {
            NotificationLog::query()->delete();
        } else {
            $this->memoryLogs = new InMemoryNotificationLogRepository;
            $this->app->instance(NotificationLogRepository::class, $this->memoryLogs);
        }
    }

    protected function tearDown(): void
    {
        if ($this->realMongo && $this->app !== null) {
            NotificationLog::query()->delete();
        }

        $this->memoryLogs?->clear();
        self::$active = null;
        parent::tearDown();
    }

    /**
     * @param  list<string>  $roles
     */
    protected function bearerToken(array $roles = ['admin'], int $userId = 1, int $ttl = 900): string
    {
        $pem = (string) file_get_contents(dirname(__DIR__, 2).'/packages/platform-kit/tests/fixtures/access-private.pem');
        $now = time();

        return new AccessTokenSigner($pem, 'test-1', 'http://auth-app', 'ecommerce')
            ->issue($userId, $roles, null, [
                'iat' => $now,
                'nbf' => $now,
                'exp' => $now + $ttl,
            ]);
    }

    protected function asRole(string $role, int $userId = 1): static
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->bearerToken([$role], $userId));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function seedLog(array $attributes): NotificationLog
    {
        if ($this->memoryLogs instanceof InMemoryNotificationLogRepository) {
            return $this->memoryLogs->seed($attributes);
        }

        if (is_string($attributes['payload'] ?? null)) {
            /** @var MongoEloquentBuilder<NotificationLog> $query */
            $query = NotificationLog::query();
            $insertedId = $query->raw(function (Collection $collection) use ($attributes): string {
                $res = $collection->insertOne([
                    'event_id' => $attributes['event_id'] ?? null,
                    'user_id' => $attributes['user_id'] ?? null,
                    'event' => $attributes['event'] ?? 'user.registered',
                    'channel' => NotificationChannel::Email->value,
                    'recipient' => $attributes['recipient'] ?? 'ann@example.com',
                    'payload' => $attributes['payload'],
                    'status' => ($attributes['status'] ?? NotificationStatus::Sent) instanceof NotificationStatus
                        ? $attributes['status']->value
                        : (string) ($attributes['status'] ?? 'sent'),
                    'attempts' => (int) ($attributes['attempts'] ?? 1),
                    'is_dlq' => (bool) ($attributes['is_dlq'] ?? false),
                    'error_message' => $attributes['error_message'] ?? null,
                ]);

                return (string) $res->getInsertedId();
            });

            return NotificationLog::query()->findOrFail((string) $insertedId);
        }

        $createdAt = $attributes['created_at'] ?? now();
        unset($attributes['created_at']);

        $log = NotificationLog::query()->create(array_merge([
            'channel' => NotificationChannel::Email,
            'status' => NotificationStatus::Sent,
            'attempts' => 1,
            'is_dlq' => false,
        ], $attributes));

        $log->created_at = $createdAt;
        $log->save();

        return $log->fresh() ?? $log;
    }

    /**
     * @return list<NotificationLog>
     */
    public function allLogs(): array
    {
        if ($this->memoryLogs instanceof InMemoryNotificationLogRepository) {
            return $this->memoryLogs->all();
        }

        /** @var list<NotificationLog> $items */
        $items = NotificationLog::query()->get()->all();

        return $items;
    }

    public function findLog(string $id): NotificationLog
    {
        return $this->app->make(NotificationLogRepository::class)->findOrFail($id);
    }

    private function detectMongo(): bool
    {
        if (! extension_loaded('mongodb')) {
            return false;
        }

        $socket = @fsockopen('127.0.0.1', 27017, $errno, $errstr, 0.2);
        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
