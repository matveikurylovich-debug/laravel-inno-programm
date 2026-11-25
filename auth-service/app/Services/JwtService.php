<?php

namespace App\Services;

use App\Contracts\AuthService;
use App\Enums\RoleName;
use App\Models\User;
use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\RefreshTokenReader;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use InvalidArgumentException;

class JwtService implements AuthService
{
    private string $refreshSecret;

    private int $accessTtl;

    private int $refreshTtl;

    public function __construct()
    {
        $this->refreshSecret = config('jwt.keys.refresh');
        $this->accessTtl = config('jwt.ttl.access');
        $this->refreshTtl = config('jwt.ttl.refresh');
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
        $roles = $user->getRoleNames()->values()->all();

        return $this->signer()->issue((int) $user->id, $roles);
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

        $token = JWT::encode($payload, $this->refreshSecret, 'HS256');

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
        try {
            $claims = $this->accessVerifier()->verify($token);
            if ($this->isBlacklisted($claims->jti)) {
                throw new InvalidArgumentException('Токен отозван (находится в блэклисте).');
            }
            $notBefore = Redis::get($this->notBeforeKey($claims->userId));
        } catch (InvalidTokenException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (TokenInfrastructureException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new TokenInfrastructureException('Revocation store is unavailable.', previous: $exception);
        }

        if (is_string($notBefore) && $notBefore !== '' && $claims->iat <= (int) $notBefore) {
            throw new InvalidArgumentException('Сессия отозвана.');
        }

        return (object) [
            'sub' => $claims->userId,
            'jti' => $claims->jti,
            'iat' => $claims->iat,
            'nbf' => $claims->nbf,
            'exp' => $claims->exp,
            'type' => $claims->type->value,
            'roles' => $claims->roles,
        ];
    }

    /**
     * Проверка Refresh-токена (подпись + наличие активного ключа в Redis)
     */
    public function decodeRefreshToken(string $token): object
    {
        $decoded = $this->refreshReader()->read($token);

        $exists = Redis::exists($this->refreshKey((int) $decoded->sub, (string) $decoded->jti));
        if (! $exists) {
            throw new InvalidArgumentException('Refresh-токен недействителен или отозван.');
        }

        return $decoded;
    }

    public function readRefreshToken(string $token): object
    {
        $decoded = $this->refreshReader()->read($token);

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

    private function accessVerifier(): AccessTokenVerifier
    {
        return app(AccessTokenVerifier::class);
    }

    private function signer(): AccessTokenSigner
    {
        $path = (string) config('jwt.private_key_path');
        if ($path === '' || ! is_file($path)) {
            throw new TokenInfrastructureException('JWT access private key is missing.');
        }

        return new AccessTokenSigner(
            privateKeyPem: (string) file_get_contents($path),
            kid: (string) config('jwt.kid'),
            issuer: (string) config('jwt.issuer'),
            audience: (string) config('jwt.audience'),
            ttl: $this->accessTtl,
        );
    }

    private function refreshReader(): RefreshTokenReader
    {
        return new RefreshTokenReader((string) $this->refreshSecret, 'HS256');
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
            $claims = $this->accessVerifier()->verify($token);
        } catch (InvalidTokenException) {
            return;
        }

        $remainingSeconds = $claims->exp - time();
        if ($remainingSeconds <= 0) {
            return;
        }

        try {
            Redis::setex($this->blacklistKey($claims->jti), $remainingSeconds, 'revoked');
        } catch (\Throwable $exception) {
            throw new TokenInfrastructureException('Revocation store is unavailable.', previous: $exception);
        }
    }

    public function isBlacklisted(string $jti): bool
    {
        return (bool) Redis::exists($this->blacklistKey($jti));
    }

    private function blacklistKey(string $jti): string
    {
        return 'jwt:blacklist:jti:'.$jti;
    }
}
