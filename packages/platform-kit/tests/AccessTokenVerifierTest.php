<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Tests;

use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use PHPUnit\Framework\TestCase;

final class AccessTokenVerifierTest extends TestCase
{
    public function test_golden_fixtures(): void
    {
        $fixtures = json_decode((string) file_get_contents(__DIR__.'/fixtures/tokens.json'), true);
        $verifier = AccessTokenVerifier::fromJwks(json_encode($fixtures['jwks']), $fixtures['issuer'], $fixtures['audience']);

        $claims = $verifier->verify($fixtures['valid']);
        $this->assertSame(7, $claims->userId);
        $this->assertSame(['customer'], $claims->roles);
        $this->assertSame('fixture-valid', $claims->jti);

        foreach (['expired', 'wrong_type', 'missing_type', 'wrong_alg', 'missing_aud', 'wrong_aud'] as $name) {
            try {
                $verifier->verify($fixtures[$name]);
                $this->fail($name.' was accepted');
            } catch (InvalidTokenException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_short_secret_is_an_infrastructure_failure(): void
    {
        $this->expectException(TokenInfrastructureException::class);

        new \Ecommerce\PlatformKit\Token\RefreshTokenReader('too-short');
    }
}
