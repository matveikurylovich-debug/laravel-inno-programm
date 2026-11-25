<?php

declare(strict_types=1);

namespace Tests\Feature;

use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Tests\TestCase;

class PlatformKitGoldenTest extends TestCase
{
    public function test_golden_access_token_is_accepted_and_the_others_are_rejected(): void
    {
        $fixtures = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/tokens.json'), true);
        $verifier = new AccessTokenVerifier($fixtures['secret']);

        $this->assertSame(7, $verifier->verify($fixtures['valid'])->userId);

        foreach (['expired', 'wrong_type', 'missing_type', 'wrong_alg'] as $name) {
            try {
                $verifier->verify($fixtures[$name]);
                $this->fail($name);
            } catch (InvalidTokenException) {
                $this->assertTrue(true);
            }
        }
    }
}
