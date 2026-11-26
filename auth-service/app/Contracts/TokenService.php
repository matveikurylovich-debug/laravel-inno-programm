<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\User;
use App\Support\TokenPair;

interface TokenService
{
    public function generateTokenPair(User $user): TokenPair;

    /**
     * @return object{sub: int, jti: string, iat: int, nbf: int, exp: int, type: string, roles: list<string>}
     */
    public function decodeAccessToken(string $token): object;

    public function decodeRefreshToken(string $token): object;

    public function readRefreshToken(string $token): object;

    public function rotateRefreshToken(string $token): TokenPair;

    public function revokeRefreshToken(int $userId, string $jti): void;

    public function revokeAllForUser(int $userId): void;

    public function blacklistAccessToken(string $token): void;
}
