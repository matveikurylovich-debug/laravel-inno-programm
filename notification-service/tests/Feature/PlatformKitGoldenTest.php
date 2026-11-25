<?php

namespace Tests\Feature;

use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Tests\HttpTestCase;

class PlatformKitGoldenTest extends HttpTestCase
{
    public function test_golden_access_token_is_accepted_and_the_others_are_rejected(): void
    {
        $fixtures = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/tokens.json'), true);
        $verifier = AccessTokenVerifier::fromJwks(json_encode($fixtures['jwks']), $fixtures['issuer'], $fixtures['audience']);

        $this->assertSame(7, $verifier->verify($fixtures['valid'])->userId);

        foreach (['expired', 'wrong_type', 'missing_type', 'wrong_alg', 'missing_aud', 'wrong_aud'] as $name) {
            try {
                $verifier->verify($fixtures[$name]);
                $this->fail($name);
            } catch (InvalidTokenException) {
                $this->assertTrue(true);
            }
        }
    }
}
