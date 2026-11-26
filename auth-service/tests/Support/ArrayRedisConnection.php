<?php

declare(strict_types=1);

namespace Tests\Support;

use Closure;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection;

/**
 * In-memory stand-in used when Redis is not running locally.
 */
final class ArrayRedisConnection extends Connection implements RedisFactory
{
    /** @var array<string, string> */
    private array $values = [];

    /** @var array<string, array<string, true>> */
    private array $sets = [];

    public function connection($name = null): self
    {
        return $this;
    }

    /**
     * @param  array<int, string>|string  $channels
     */
    public function createSubscription($channels, Closure $callback, $method = 'subscribe'): void {}

    /**
     * @param  string  $method
     * @param  array<int, mixed>  $parameters
     */
    public function command($method, array $parameters = []): mixed
    {
        return match ($method) {
            'setex' => $this->setex((string) ($parameters[0] ?? ''), (int) ($parameters[1] ?? 0), (string) ($parameters[2] ?? '')),
            'exists' => $this->exists((string) ($parameters[0] ?? '')),
            'get' => $this->get((string) ($parameters[0] ?? '')),
            'expire' => $this->expire((string) ($parameters[0] ?? ''), (int) ($parameters[1] ?? 0)),
            'sadd' => $this->sadd((string) ($parameters[0] ?? ''), ...array_map(strval(...), array_slice($parameters, 1))),
            'srem' => $this->srem((string) ($parameters[0] ?? ''), ...array_map(strval(...), array_slice($parameters, 1))),
            'smembers' => $this->smembers((string) ($parameters[0] ?? '')),
            'del' => $this->del(...array_map(strval(...), $parameters)),
            'flushdb' => $this->flushdb(),
            default => null,
        };
    }

    public function setex(string $key, int $ttl, string $value): bool
    {
        $this->values[$key] = $value;

        return true;
    }

    public function exists(string $key): int
    {
        return array_key_exists($key, $this->values) ? 1 : 0;
    }

    public function get(string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function expire(string $key, int $ttl): bool
    {
        return array_key_exists($key, $this->values) || isset($this->sets[$key]);
    }

    public function sadd(string $key, string ...$members): int
    {
        $added = 0;
        foreach ($members as $member) {
            if (! isset($this->sets[$key][$member])) {
                $this->sets[$key][$member] = true;
                $added++;
            }
        }

        return $added;
    }

    public function srem(string $key, string ...$members): int
    {
        $removed = 0;
        foreach ($members as $member) {
            if (isset($this->sets[$key][$member])) {
                unset($this->sets[$key][$member]);
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return list<string>
     */
    public function smembers(string $key): array
    {
        return array_keys($this->sets[$key] ?? []);
    }

    public function del(string ...$keys): int
    {
        $removed = 0;
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->values)) {
                unset($this->values[$key]);
                $removed++;
            }
            if (isset($this->sets[$key])) {
                unset($this->sets[$key]);
                $removed++;
            }
        }

        return $removed;
    }

    public function flushdb(): bool
    {
        $this->values = [];
        $this->sets = [];

        return true;
    }
}
