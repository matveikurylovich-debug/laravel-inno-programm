<?php

declare(strict_types=1);

use Ecommerce\PlatformKit\Token\AccessClaims;
use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;

it('accepts golden access token and rejects tampered or expired tokens', function (): void {
    $raw = (string) file_get_contents(dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/tokens.json');
    /** @var array<string, mixed> $fixtures */
    $fixtures = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    $jwks = json_encode($fixtures['jwks'], JSON_THROW_ON_ERROR);
    $issuer = is_string($fixtures['issuer'] ?? null) ? $fixtures['issuer'] : '';
    $audience = is_string($fixtures['audience'] ?? null) ? $fixtures['audience'] : '';
    $validToken = is_string($fixtures['valid'] ?? null) ? $fixtures['valid'] : '';

    $verifier = AccessTokenVerifier::fromJwks($jwks, $issuer, $audience);

    expect($verifier->verify($validToken)->userId)->toBe(7);

    foreach (['expired', 'wrong_type', 'missing_type', 'wrong_alg', 'missing_aud', 'wrong_aud'] as $name) {
        $token = is_string($fixtures[$name] ?? null) ? $fixtures[$name] : '';
        expect(static fn (): AccessClaims => $verifier->verify($token))->toThrow(InvalidTokenException::class);
    }
});
