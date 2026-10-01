<?php

namespace App\Services;

use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use InvalidArgumentException;

class JwtService
{
    private string $accessSecret;

    private string $refreshSecret;

    private int $accessTtl;

    private int $refreshTtl;

    private string $algo;

    public function __construct()
    {
        $this->accessSecret = config('jwt.keys.access');
        $this->refreshSecret = config('jwt.keys.refresh');
        $this->accessTtl = config('jwt.ttl.access');
        $this->refreshTtl = config('jwt.ttl.refresh');
        $this->algo = config('jwt.algo');
    }

    /**
     * Генерация пары: Access + Refresh токены
     */
    public function generateTokenPair(User $user): array
    {
        return [
            'access_token' => $this->createAccessToken($user),
            'refresh_token' => $this->createRefreshToken($user),
            'token_type' => 'Bearer',
            'expires_in' => $this->accessTtl,
        ];
    }

    /**
     * Выпуск Access-токена с ролями Spatie
     */
    public function createAccessToken(User $user): string
    {
        $now = time();
        $roles = $user->getRoleNames()->values()->all();
        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'email' => $user->email,
            'role' => $this->primaryRole($user),
            'roles' => $roles,
            'type' => 'access',
            'iat' => $now,
            'exp' => $now + $this->accessTtl,
        ];

        return JWT::encode($payload, $this->accessSecret, $this->algo);
    }

    /**
     * Основная роль для claim `role` и ответа API.
     */
    public function primaryRole(User $user): string
    {
        $roles = $user->getRoleNames();

        foreach (['admin', 'analyst', 'customer'] as $role) {
            if ($roles->contains($role)) {
                return $role;
            }
        }

        return (string) ($roles->first() ?? 'customer');
    }

    /**
     * Выпуск Refresh-токена и сохранение его ID (jti) в Redis
     */
    public function createRefreshToken(User $user): string
    {
        $now = time();
        $jti = Str::uuid()->toString();

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'jti' => $jti,
            'type' => 'refresh',
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
        ];

        $token = JWT::encode($payload, $this->refreshSecret, $this->algo);

        // Кладём в Redis: ключ живёт ровно столько, сколько сам токен
        Redis::setex("jwt:refresh:{$user->id}:{$jti}", $this->refreshTtl, $token);

        return $token;
    }

    /**
     * Проверка Access-токена (подпись + отсутствие в блэклисте)
     */
    public function decodeAccessToken(string $token): object
    {
        if ($this->isBlacklisted($token)) {
            throw new InvalidArgumentException('Токен отозван (находится в блэклисте).');
        }

        $decoded = JWT::decode($token, new Key($this->accessSecret, $this->algo));

        if (($decoded->type ?? null) !== 'access') {
            throw new InvalidArgumentException('Некорректный тип токена.');
        }

        return $decoded;
    }

    /**
     * Проверка Refresh-токена (подпись + наличие активного ключа в Redis)
     */
    public function decodeRefreshToken(string $token): object
    {
        $decoded = JWT::decode($token, new Key($this->refreshSecret, $this->algo));

        if (($decoded->type ?? null) !== 'refresh') {
            throw new InvalidArgumentException('Некорректный тип токена.');
        }

        $exists = Redis::exists("jwt:refresh:{$decoded->sub}:{$decoded->jti}");
        if (! $exists) {
            throw new InvalidArgumentException('Refresh-токен недействителен или отозван.');
        }

        return $decoded;
    }

    /**
     * Удаление использованного или отозванного Refresh-токена из Redis
     */
    public function revokeRefreshToken(int $userId, string $jti): void
    {
        Redis::del("jwt:refresh:{$userId}:{$jti}");
    }

    /**
     * Помещение Access-токена в блэклист в Redis на остаток времени его жизни
     */
    public function blacklistAccessToken(string $token): void
    {
        try {
            $decoded = JWT::decode($token, new Key($this->accessSecret, $this->algo));
            $remainingSeconds = $decoded->exp - time();

            if ($remainingSeconds > 0) {
                Redis::setex("jwt:blacklist:{$token}", $remainingSeconds, 'revoked');
            }
        } catch (\Throwable) {
            // Если токен уже битый или просроченный, в блэклист его писать не нужно
        }
    }

    public function isBlacklisted(string $token): bool
    {
        return (bool) Redis::exists("jwt:blacklist:{$token}");
    }
}
