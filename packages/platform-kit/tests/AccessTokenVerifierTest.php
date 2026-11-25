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
        $verifier = new AccessTokenVerifier($fixtures['secret']);

        $claims = $verifier->verify($fixtures['valid']);
        $this->assertSame(7, $claims->userId);
        $this->assertSame(['customer'], $claims->roles);

        foreach (['expired', 'wrong_type', 'missing_type', 'wrong_alg'] as $name) {
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

        new AccessTokenVerifier('too-short');
    }
}
