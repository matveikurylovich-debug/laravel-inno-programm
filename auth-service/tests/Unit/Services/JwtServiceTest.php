<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\ArrayRefreshTokenStore;
use App\Services\Auth\JwtService;
use Carbon\CarbonImmutable;
use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Firebase\JWT\JWT;

/**
 * @param  list<string>  $roles
 */
function makeInMemoryUser(int $id = 1, array $roles = ['customer']): User
{
    $user = new User;
    $user->setRawAttributes([
        'id' => $id,
        'email' => "user{$id}@example.com",
        'name' => "User {$id}",
    ]);
    $user->setRelation(
        'roles',
        collect(array_map(static fn (string $role): object => (object) ['name' => $role], $roles)),
    );

    return $user;
}

/**
 * @param  array<int, User>  $users
 * @return array{service: JwtService, store: ArrayRefreshTokenStore, privateKeyPath: string, refreshSecret: string}
 */
function makePureJwtService(array $users = []): array
{
    $fixturesDir = dirname(__DIR__, 4).'/packages/platform-kit/tests/fixtures';
    $jwks = (string) file_get_contents($fixturesDir.'/jwks.json');
    $privateKeyPath = $fixturesDir.'/access-private.pem';
    $refreshSecret = 'testing-jwt-refresh-secret-32b!!';

    $store = new ArrayRefreshTokenStore;
    $verifier = AccessTokenVerifier::fromJwks($jwks, 'http://auth-app', 'ecommerce');

    $service = new JwtService(
        store: $store,
        verifier: $verifier,
        refreshSecret: $refreshSecret,
        privateKeyPath: $privateKeyPath,
        kid: 'test-1',
        issuer: 'http://auth-app',
        audience: 'ecommerce',
        appUrl: 'http://auth-app',
        accessTtl: 900,
        refreshTtl: 1209600,
        refreshGraceSeconds: 30,
        userResolver: static fn (int $id): ?User => $users[$id] ?? null,
    );

    return [
        'service' => $service,
        'store' => $store,
        'privateKeyPath' => $privateKeyPath,
        'refreshSecret' => $refreshSecret,
    ];
}

afterEach(function (): void {
    CarbonImmutable::setTestNow();
});

it('generates a valid token pair with roles using in-memory store', function (): void {
    CarbonImmutable::setTestNow('2026-11-25T12:00:00Z');
    $user = makeInMemoryUser(42, ['customer', 'admin']);
    ['service' => $service] = makePureJwtService([$user->id => $user]);

    $tokens = $service->generateTokenPair($user);

    expect($tokens->accessToken)->not->toBe('')
        ->and($tokens->refreshToken)->not->toBe('')
        ->and($tokens->tokenType)->toBe('Bearer')
        ->and($tokens->expiresIn)->toBe(900);

    $payload = $service->decodeAccessToken($tokens->accessToken);
    $rawClaims = (array) $payload;

    expect($payload->sub)->toBe(42)
        ->and(array_key_exists('email', $rawClaims))->toBeFalse()
        ->and(array_key_exists('role', $rawClaims))->toBeFalse()
        ->and($payload->type)->toBe('access')
        ->and($payload->roles)->toBe(['customer', 'admin'])
        ->and($payload->jti)->not->toBe('')
        ->and($payload->exp)->toBe(CarbonImmutable::now()->getTimestamp() + 900);
});

it('rejects an access token with a tampered signature', function (): void {
    $user = makeInMemoryUser(7);
    ['service' => $service] = makePureJwtService([$user->id => $user]);

    $tokens = $service->generateTokenPair($user);
    $parts = explode('.', $tokens->accessToken);
    $parts[2] = ($parts[2][0] === 'A' ? 'B' : 'A').substr($parts[2], 1);
    $tampered = implode('.', $parts);

    expect(fn (): object => $service->decodeAccessToken($tampered))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects an expired access token', function (): void {
    CarbonImmutable::setTestNow('2026-11-25T12:00:00Z');
    $now = CarbonImmutable::now()->getTimestamp();
    ['service' => $service, 'privateKeyPath' => $privateKeyPath] = makePureJwtService();

    $pem = (string) file_get_contents($privateKeyPath);
    $expiredToken = new AccessTokenSigner($pem, 'test-1', 'http://auth-app', 'ecommerce')
        ->issue(1, ['customer'], null, [
            'iat' => $now - 3600,
            'nbf' => $now - 3600,
            'exp' => $now - 100,
        ]);

    expect(fn (): object => $service->decodeAccessToken($expiredToken))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects token type mismatches in both access and refresh decoders', function (): void {
    CarbonImmutable::setTestNow('2026-11-25T12:00:00Z');
    $now = CarbonImmutable::now()->getTimestamp();
    $user = makeInMemoryUser(9);
    ['service' => $service, 'refreshSecret' => $refreshSecret] = makePureJwtService([$user->id => $user]);

    $tokens = $service->generateTokenPair($user);

    expect(fn (): object => $service->decodeAccessToken($tokens->refreshToken))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): object => $service->decodeRefreshToken($tokens->accessToken))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): object => $service->rotateRefreshToken($tokens->accessToken))
        ->toThrow(InvalidArgumentException::class);

    $forgedAccessSignedWithRefreshSecret = JWT::encode([
        'iss' => 'http://auth-app',
        'sub' => 9,
        'jti' => 'forged-jti',
        'type' => 'access',
        'iat' => $now,
        'exp' => $now + 900,
    ], $refreshSecret, 'HS256');

    expect(fn (): object => $service->decodeRefreshToken($forgedAccessSignedWithRefreshSecret))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): object => $service->rotateRefreshToken($forgedAccessSignedWithRefreshSecret))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects a blacklisted access token', function (): void {
    CarbonImmutable::setTestNow('2026-11-25T12:00:00Z');
    $user = makeInMemoryUser(5);
    ['service' => $service] = makePureJwtService([$user->id => $user]);

    $tokens = $service->generateTokenPair($user);
    $service->blacklistAccessToken($tokens->accessToken);

    expect(fn (): object => $service->decodeAccessToken($tokens->accessToken))
        ->toThrow(InvalidArgumentException::class, 'Токен отозван (находится в блэклисте).');
});

it('rotates refresh tokens with grace window and revokes all sessions on replay after grace', function (): void {
    CarbonImmutable::setTestNow('2026-11-25T12:00:00Z');
    $user = makeInMemoryUser(11, ['customer']);
    ['service' => $service] = makePureJwtService([$user->id => $user]);

    $initial = $service->generateTokenPair($user);
    $rotated = $service->rotateRefreshToken($initial->refreshToken);
    $withinGrace = $service->rotateRefreshToken($initial->refreshToken);

    expect($withinGrace->refreshToken)->toBe($rotated->refreshToken);

    CarbonImmutable::setTestNow('2026-11-25T12:01:00Z');

    expect(fn (): object => $service->rotateRefreshToken($initial->refreshToken))
        ->toThrow(InvalidArgumentException::class, 'Повторное использование refresh-токена.');

    expect(fn (): object => $service->decodeRefreshToken($rotated->refreshToken))
        ->toThrow(InvalidArgumentException::class);

    expect(fn (): object => $service->decodeAccessToken($rotated->accessToken))
        ->toThrow(InvalidArgumentException::class, 'Сессия отозвана.');
});
