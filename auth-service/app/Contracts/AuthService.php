<?php

namespace App\Contracts;

use App\Models\User;

interface AuthService
{
    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function generateTokenPair(User $user): array;

    public function createAccessToken(User $user): string;

    public function primaryRole(User $user): string;

    public function createRefreshToken(User $user): string;

    public function decodeAccessToken(string $token): object;

    public function decodeRefreshToken(string $token): object;

    public function revokeRefreshToken(int $userId, string $jti): void;

    public function blacklistAccessToken(string $token): void;

    public function isBlacklisted(string $token): bool;
}
