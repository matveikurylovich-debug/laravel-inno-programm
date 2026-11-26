<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\RefreshTokenStore;
use App\Contracts\TokenService;
use App\Models\User;
use App\Support\TokenPair;
use Carbon\CarbonImmutable;
use Closure;
use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\RefreshTokenReader;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Firebase\JWT\JWT;
use Illuminate\Container\Attributes\Config;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

class JwtService implements TokenService
{
    /**
     * @param  (Closure(int): ?User)|null  $userResolver
     */
    public function __construct(
        private readonly RefreshTokenStore $store,
        private readonly AccessTokenVerifier $verifier,
        #[Config('jwt.keys.refresh', '')]
        private readonly string $refreshSecret = '',
        #[Config('jwt.private_key_path', '')]
        private readonly string $privateKeyPath = '',
        #[Config('jwt.kid', 'auth-1')]
        private readonly string $kid = 'auth-1',
        #[Config('jwt.issuer', 'http://auth-app')]
        private readonly string $issuer = 'http://auth-app',
        #[Config('jwt.audience', 'ecommerce')]
        private readonly string $audience = 'ecommerce',
        #[Config('app.url', 'http://localhost')]
        private readonly string $appUrl = 'http://localhost',
        #[Config('jwt.ttl.access', 900)]
        private readonly int $accessTtl = 900,
        #[Config('jwt.ttl.refresh', 1209600)]
        private readonly int $refreshTtl = 1209600,
        #[Config('jwt.refresh_grace_seconds', 30)]
        private readonly int $refreshGraceSeconds = 30,
        private readonly ?Closure $userResolver = null,
    ) {}

    public function generateTokenPair(User $user): TokenPair
    {
        return new TokenPair(
            accessToken: $this->createAccessToken($user),
            refreshToken: $this->createRefreshToken($user),
            tokenType: 'Bearer',
            expiresIn: $this->accessTtl,
        );
    }

    /**
     * @return object{sub: int, jti: string, iat: int, nbf: int, exp: int, type: string, roles: list<string>}
     */
    public function decodeAccessToken(string $token): object
    {
        JWT::$timestamp = CarbonImmutable::now()->getTimestamp();

        try {
            $claims = $this->verifier->verify($token);
            if ($this->store->isAccessBlacklisted($claims->jti)) {
                throw new InvalidArgumentException('Токен отозван (находится в блэклисте).');
            }
            $notBefore = $this->store->getNotBefore($claims->userId);
        } catch (InvalidTokenException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        } catch (InvalidArgumentException|TokenInfrastructureException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TokenInfrastructureException('Revocation store is unavailable.', previous: $exception);
        } finally {
            JWT::$timestamp = null;
        }

        if ($notBefore !== null && $claims->iat <= $notBefore) {
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

    public function decodeRefreshToken(string $token): object
    {
        $decoded = $this->readRefreshToken($token);
        $userId = (int) ($decoded->sub ?? 0);
        $jti = (string) ($decoded->jti ?? '');

        if (! $this->store->hasRefresh($userId, $jti)) {
            throw new InvalidArgumentException('Refresh-токен недействителен или отозван.');
        }

        return $decoded;
    }

    public function readRefreshToken(string $token): object
    {
        JWT::$timestamp = CarbonImmutable::now()->getTimestamp();

        try {
            return $this->refreshReader()->read($token);
        } catch (InvalidTokenException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), previous: $exception);
        } finally {
            JWT::$timestamp = null;
        }
    }

    public function rotateRefreshToken(string $token): TokenPair
    {
        $decoded = $this->readRefreshToken($token);
        $userId = (int) ($decoded->sub ?? 0);
        $jti = (string) ($decoded->jti ?? '');

        if ($this->store->consumeRefresh($userId, $jti)) {
            $user = $this->resolveUser($userId);
            if (! $user instanceof User) {
                throw new InvalidArgumentException('Пользователь не найден.');
            }

            $pair = $this->generateTokenPair($user);
            $this->store->putGrace($userId, $jti, $pair, $this->refreshGraceSeconds);

            return $pair;
        }

        $gracePair = $this->store->getGrace($userId, $jti);
        if ($gracePair instanceof TokenPair) {
            return $gracePair;
        }

        $this->revokeAllForUser($userId);

        throw new InvalidArgumentException('Повторное использование refresh-токена.');
    }

    public function revokeRefreshToken(int $userId, string $jti): void
    {
        $this->store->revokeRefresh($userId, $jti);
    }

    public function revokeAllForUser(int $userId): void
    {
        $this->store->revokeAllForUser(
            userId: $userId,
            notBeforeTimestamp: CarbonImmutable::now()->getTimestamp(),
            accessTtlSeconds: $this->accessTtl,
        );
    }

    public function blacklistAccessToken(string $token): void
    {
        $now = CarbonImmutable::now()->getTimestamp();
        JWT::$timestamp = $now;

        try {
            $claims = $this->verifier->verify($token);
        } catch (InvalidTokenException) {
            return;
        } finally {
            JWT::$timestamp = null;
        }

        $remainingSeconds = $claims->exp - $now;
        if ($remainingSeconds <= 0) {
            return;
        }

        try {
            $this->store->blacklistAccess($claims->jti, $remainingSeconds);
        } catch (TokenInfrastructureException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TokenInfrastructureException('Revocation store is unavailable.', previous: $exception);
        }
    }

    private function createAccessToken(User $user): string
    {
        $roleNames = $user->relationLoaded('roles')
            ? $user->getRelation('roles')->pluck('name')
            : $user->getRoleNames();

        /** @var list<string> $roles */
        $roles = $roleNames->map(fn (mixed $role): string => (string) $role)->values()->all();
        $now = CarbonImmutable::now()->getTimestamp();

        return $this->signer()->issue((int) $user->id, $roles, null, [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $this->accessTtl,
        ]);
    }

    private function createRefreshToken(User $user): string
    {
        $now = CarbonImmutable::now()->getTimestamp();
        $jti = Str::uuid()->toString();
        $userId = (int) $user->id;

        $payload = [
            'iss' => $this->appUrl,
            'sub' => $userId,
            'jti' => $jti,
            'type' => 'refresh',
            'iat' => $now,
            'exp' => $now + $this->refreshTtl,
        ];

        $token = JWT::encode($payload, $this->refreshSecret, 'HS256');
        $this->store->putRefresh($userId, $jti, $this->refreshTtl);

        return $token;
    }

    private function signer(): AccessTokenSigner
    {
        if ($this->privateKeyPath === '' || ! is_file($this->privateKeyPath)) {
            throw new TokenInfrastructureException('JWT access private key is missing.');
        }

        return new AccessTokenSigner(
            privateKeyPem: (string) file_get_contents($this->privateKeyPath),
            kid: $this->kid,
            issuer: $this->issuer,
            audience: $this->audience,
            ttl: $this->accessTtl,
        );
    }

    private function refreshReader(): RefreshTokenReader
    {
        return new RefreshTokenReader($this->refreshSecret, 'HS256');
    }

    private function resolveUser(int $userId): ?User
    {
        if ($this->userResolver instanceof Closure) {
            return ($this->userResolver)($userId);
        }

        return User::query()->find($userId);
    }
}
