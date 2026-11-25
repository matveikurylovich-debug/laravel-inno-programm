<?php

namespace App\Services;

use App\Contracts\AuthService;
use App\Enums\RoleName;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use InvalidArgumentException;

class JwtService implements AuthService
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

        foreach (RoleName::cases() as $role) {
            if ($roles->contains($role->value)) {
                return $role->value;
            }
        }

        return RoleName::Customer->value;
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

        Redis::setex($this->refreshKey($user->id, $jti), $this->refreshTtl, '1');
        Redis::sadd($this->userSetKey($user->id), $jti);
        Redis::expire($this->userSetKey($user->id), $this->refreshTtl);

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

        $notBefore = Redis::get($this->notBeforeKey((int) $decoded->sub));
        if (is_string($notBefore) && $notBefore !== '' && (int) ($decoded->iat ?? 0) <= (int) $notBefore) {
            throw new InvalidArgumentException('Сессия отозвана.');
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

        $exists = Redis::exists($this->refreshKey((int) $decoded->sub, (string) $decoded->jti));
        if (! $exists) {
            throw new InvalidArgumentException('Refresh-токен недействителен или отозван.');
        }

        return $decoded;
    }

    public function readRefreshToken(string $token): object
    {
        $decoded = JWT::decode($token, new Key($this->refreshSecret, $this->algo));

        if (($decoded->type ?? null) !== 'refresh') {
            throw new InvalidArgumentException('Некорректный тип токена.');
        }

        return $decoded;
    }

    public function consumeRefreshToken(string $token): object
    {
        $decoded = $this->readRefreshToken($token);
        $userId = (int) $decoded->sub;
        $jti = (string) $decoded->jti;
        $removed = (int) Redis::del($this->refreshKey($userId, $jti));
        Redis::srem($this->userSetKey($userId), $jti);

        if ($removed === 1) {
            return $decoded;
        }

        $this->revokeAllForUser($userId);

        throw new InvalidArgumentException('Повторное использование refresh-токена.');
    }

    /**
     * @return array{access_token: string, refresh_token: string, token_type: string, expires_in: int}
     */
    public function rotateRefreshToken(string $token): array
    {
        $decoded = $this->readRefreshToken($token);
        $userId = (int) $decoded->sub;
        $jti = (string) $decoded->jti;
        $graceKey = $this->graceKey($userId, $jti);
        $removed = (int) Redis::del($this->refreshKey($userId, $jti));
        Redis::srem($this->userSetKey($userId), $jti);

        if ($removed === 1) {
            $user = User::query()->findOrFail($userId);
            $pair = $this->generateTokenPair($user);
            Redis::setex($graceKey, (int) config('jwt.refresh_grace_seconds', 30), json_encode($pair));

            return $pair;
        }

        $cached = Redis::get($graceKey);
        if (is_string($cached) && $cached !== '') {
            $pair = json_decode($cached, true);
            if (is_array($pair) && isset($pair['access_token'], $pair['refresh_token'])) {
                return $pair;
            }
        }

        $this->revokeAllForUser($userId);

        throw new InvalidArgumentException('Повторное использование refresh-токена.');
    }

    private function graceKey(int $userId, string $jti): string
    {
        return "jwt:refresh:grace:{$userId}:{$jti}";
    }

    /**
     * Удаление использованного или отозванного Refresh-токена из Redis
     */
    public function revokeRefreshToken(int $userId, string $jti): void
    {
        Redis::del($this->refreshKey($userId, $jti));
        Redis::srem($this->userSetKey($userId), $jti);
    }

    public function revokeAllForUser(int $userId): void
    {
        $jtis = Redis::smembers($this->userSetKey($userId));
        foreach (is_array($jtis) ? $jtis : [] as $jti) {
            Redis::del($this->refreshKey($userId, (string) $jti));
        }

        Redis::del($this->userSetKey($userId));
        Redis::setex($this->notBeforeKey($userId), $this->accessTtl, (string) time());
    }

    private function refreshKey(int $userId, string $jti): string
    {
        return "jwt:refresh:{$userId}:{$jti}";
    }

    private function userSetKey(int $userId): string
    {
        return "jwt:user:{$userId}:refresh";
    }

    private function notBeforeKey(int $userId): string
    {
        return "jwt:user:{$userId}:nbf";
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
