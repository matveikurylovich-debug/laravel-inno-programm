<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Support\TokenPair;

interface RefreshTokenStore
{
    public function putRefresh(int $userId, string $jti, int $ttlSeconds): void;

    public function hasRefresh(int $userId, string $jti): bool;

    public function consumeRefresh(int $userId, string $jti): bool;

    public function revokeRefresh(int $userId, string $jti): void;

    public function putGrace(int $userId, string $jti, TokenPair $pair, int $ttlSeconds): void;

    public function getGrace(int $userId, string $jti): ?TokenPair;

    public function forgetGrace(int $userId, string $jti): void;

    public function revokeAllForUser(int $userId, int $notBeforeTimestamp, int $accessTtlSeconds): void;

    public function getNotBefore(int $userId): ?int;

    public function blacklistAccess(string $jti, int $ttlSeconds): void;

    public function isAccessBlacklisted(string $jti): bool;
}
