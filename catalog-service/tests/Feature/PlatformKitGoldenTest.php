<?php

declare(strict_types=1);

use Ecommerce\PlatformKit\Token\AccessClaims;
use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;

it('accepts the golden access token and rejects invalid token fixtures', function (): void {
    /** @var array{jwks: array<string, mixed>, issuer: string, audience: string, valid: string, expired: string, wrong_type: string, missing_type: string, wrong_alg: string, missing_aud: string, wrong_aud: string} $fixtures */
    $fixtures = json_decode(
        (string) file_get_contents(dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/tokens.json'),
        true,
    );

    $verifier = AccessTokenVerifier::fromJwks(
        (string) json_encode($fixtures['jwks']),
        $fixtures['issuer'],
        $fixtures['audience'],
    );

    expect($verifier->verify($fixtures['valid'])->userId)->toBe(7);

    foreach (['expired', 'wrong_type', 'missing_type', 'wrong_alg', 'missing_aud', 'wrong_aud'] as $name) {
        expect(static fn (): AccessClaims => $verifier->verify($fixtures[$name]))->toThrow(InvalidTokenException::class);
    }
});
