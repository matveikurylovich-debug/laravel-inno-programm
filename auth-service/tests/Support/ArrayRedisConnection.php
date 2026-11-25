<?php

namespace Tests\Support;

/**
 * In-memory stand-in used when Redis is not running, so the suite does not need the docker stack.
 */
final class ArrayRedisConnection
{
    /** @var array<string, string> */
    private array $values = [];

    public function setex(string $key, int $ttl, string $value): bool
    {
        $this->values[$key] = $value;

        return true;
    }

    public function exists(string $key): int
    {
        return array_key_exists($key, $this->values) ? 1 : 0;
    }

    public function del(string ...$keys): int
    {
        $removed = 0;
        foreach ($keys as $key) {
            if (array_key_exists($key, $this->values)) {
                unset($this->values[$key]);
                $removed++;
            }
        }

        return $removed;
    }

    public function flushdb(): bool
    {
        $this->values = [];

        return true;
    }
}
