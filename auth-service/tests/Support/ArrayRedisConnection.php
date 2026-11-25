<?php

namespace Tests\Support;

/**
 * In-memory stand-in used when Redis is not running, so the suite does not need the docker stack.
 */
final class ArrayRedisConnection
{
    /** @var array<string, string> */
    private array $values = [];

    /** @var array<string, array<string, true>> */
    private array $sets = [];

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
