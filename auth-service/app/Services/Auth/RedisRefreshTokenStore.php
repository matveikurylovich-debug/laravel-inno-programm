<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\RefreshTokenStore;
use App\Support\JwtRedisKey;
use App\Support\TokenPair;
use Illuminate\Contracts\Redis\Factory as RedisFactory;

final readonly class RedisRefreshTokenStore implements RefreshTokenStore
{
    public function __construct(
        private RedisFactory $redis,
    ) {}

    public function putRefresh(int $userId, string $jti, int $ttlSeconds): void
    {
        $setKey = JwtRedisKey::userRefreshSet($userId);

        $this->command('setex', [JwtRedisKey::refresh($userId, $jti), $ttlSeconds, '1']);
        $this->command('sadd', [$setKey, $jti]);
        $this->command('expire', [$setKey, $ttlSeconds]);
    }

    public function hasRefresh(int $userId, string $jti): bool
    {
        return (int) $this->command('exists', [JwtRedisKey::refresh($userId, $jti)]) > 0;
    }

    public function consumeRefresh(int $userId, string $jti): bool
    {
        $removed = (int) $this->command('del', [JwtRedisKey::refresh($userId, $jti)]);
        $this->command('srem', [JwtRedisKey::userRefreshSet($userId), $jti]);

        return $removed === 1;
    }

    public function revokeRefresh(int $userId, string $jti): void
    {
        $this->command('del', [JwtRedisKey::refresh($userId, $jti)]);
        $this->command('srem', [JwtRedisKey::userRefreshSet($userId), $jti]);
    }

    public function putGrace(int $userId, string $jti, TokenPair $pair, int $ttlSeconds): void
    {
        $this->command('setex', [
            JwtRedisKey::grace($userId, $jti),
            $ttlSeconds,
            json_encode($pair->toArray(), JSON_THROW_ON_ERROR),
        ]);
    }

    public function getGrace(int $userId, string $jti): ?TokenPair
    {
        $cached = $this->command('get', [JwtRedisKey::grace($userId, $jti)]);
        if (! is_string($cached) || $cached === '') {
            return null;
        }

        $pair = json_decode($cached, true);
        if (! is_array($pair) || ! isset($pair['access_token'], $pair['refresh_token'], $pair['token_type'], $pair['expires_in'])) {
            return null;
        }

        if (! is_string($pair['access_token']) || ! is_string($pair['refresh_token']) || ! is_string($pair['token_type']) || ! is_numeric($pair['expires_in'])) {
            return null;
        }

        return TokenPair::fromArray([
            'access_token' => $pair['access_token'],
            'refresh_token' => $pair['refresh_token'],
            'token_type' => $pair['token_type'],
            'expires_in' => (int) $pair['expires_in'],
        ]);
    }

    public function forgetGrace(int $userId, string $jti): void
    {
        $this->command('del', [JwtRedisKey::grace($userId, $jti)]);
    }

    public function revokeAllForUser(int $userId, int $notBeforeTimestamp, int $accessTtlSeconds): void
    {
        $setKey = JwtRedisKey::userRefreshSet($userId);
        $jtis = $this->command('smembers', [$setKey]);
        if (is_array($jtis)) {
            foreach ($jtis as $jti) {
                if (is_string($jti) || is_int($jti)) {
                    $this->command('del', [JwtRedisKey::refresh($userId, (string) $jti)]);
                }
            }
        }

        $this->command('del', [$setKey]);
        $this->command('setex', [JwtRedisKey::notBefore($userId), $accessTtlSeconds, (string) $notBeforeTimestamp]);
    }

    public function getNotBefore(int $userId): ?int
    {
        $value = $this->command('get', [JwtRedisKey::notBefore($userId)]);
        if (! is_string($value) || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }

    public function blacklistAccess(string $jti, int $ttlSeconds): void
    {
        if ($ttlSeconds <= 0) {
            return;
        }

        $this->command('setex', [JwtRedisKey::blacklist($jti), $ttlSeconds, 'revoked']);
    }

    public function isAccessBlacklisted(string $jti): bool
    {
        return (int) $this->command('exists', [JwtRedisKey::blacklist($jti)]) > 0;
    }

    /**
     * @param  list<mixed>  $parameters
     */
    private function command(string $method, array $parameters): mixed
    {
        return $this->redis->connection()->command($method, $parameters);
    }
}
