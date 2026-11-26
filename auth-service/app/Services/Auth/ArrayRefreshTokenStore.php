<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\RefreshTokenStore;
use App\Support\TokenPair;
use Carbon\CarbonImmutable;

final class ArrayRefreshTokenStore implements RefreshTokenStore
{
    /** @var array<int, array<string, int>> */
    private array $refreshTokens = [];

    /** @var array<int, array<string, array{pair: TokenPair, expires_at: int}>> */
    private array $gracePairs = [];

    /** @var array<int, array{not_before: int, expires_at: int}> */
    private array $notBefore = [];

    /** @var array<string, int> */
    private array $blacklist = [];

    public function putRefresh(int $userId, string $jti, int $ttlSeconds): void
    {
        $this->refreshTokens[$userId][$jti] = $this->now() + $ttlSeconds;
    }

    public function hasRefresh(int $userId, string $jti): bool
    {
        $expiresAt = $this->refreshTokens[$userId][$jti] ?? null;
        if ($expiresAt === null) {
            return false;
        }

        if ($expiresAt <= $this->now()) {
            unset($this->refreshTokens[$userId][$jti]);

            return false;
        }

        return true;
    }

    public function consumeRefresh(int $userId, string $jti): bool
    {
        $active = $this->hasRefresh($userId, $jti);
        unset($this->refreshTokens[$userId][$jti]);

        return $active;
    }

    public function revokeRefresh(int $userId, string $jti): void
    {
        unset($this->refreshTokens[$userId][$jti]);
    }

    public function putGrace(int $userId, string $jti, TokenPair $pair, int $ttlSeconds): void
    {
        $this->gracePairs[$userId][$jti] = [
            'pair' => $pair,
            'expires_at' => $this->now() + $ttlSeconds,
        ];
    }

    public function getGrace(int $userId, string $jti): ?TokenPair
    {
        $entry = $this->gracePairs[$userId][$jti] ?? null;
        if ($entry === null) {
            return null;
        }

        if ($entry['expires_at'] <= $this->now()) {
            unset($this->gracePairs[$userId][$jti]);

            return null;
        }

        return $entry['pair'];
    }

    public function forgetGrace(int $userId, string $jti): void
    {
        unset($this->gracePairs[$userId][$jti]);
    }

    public function revokeAllForUser(int $userId, int $notBeforeTimestamp, int $accessTtlSeconds): void
    {
        unset($this->refreshTokens[$userId]);
        $this->notBefore[$userId] = [
            'not_before' => $notBeforeTimestamp,
            'expires_at' => $this->now() + $accessTtlSeconds,
        ];
    }

    public function getNotBefore(int $userId): ?int
    {
        $entry = $this->notBefore[$userId] ?? null;
        if ($entry === null) {
            return null;
        }

        if ($entry['expires_at'] <= $this->now()) {
            unset($this->notBefore[$userId]);

            return null;
        }

        return $entry['not_before'];
    }

    public function blacklistAccess(string $jti, int $ttlSeconds): void
    {
        if ($ttlSeconds <= 0) {
            return;
        }

        $this->blacklist[$jti] = $this->now() + $ttlSeconds;
    }

    public function isAccessBlacklisted(string $jti): bool
    {
        $expiresAt = $this->blacklist[$jti] ?? null;
        if ($expiresAt === null) {
            return false;
        }

        if ($expiresAt <= $this->now()) {
            unset($this->blacklist[$jti]);

            return false;
        }

        return true;
    }

    private function now(): int
    {
        return CarbonImmutable::now()->getTimestamp();
    }
}
