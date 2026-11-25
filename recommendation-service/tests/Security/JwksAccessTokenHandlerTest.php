<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\JwksAccessTokenHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class JwksAccessTokenHandlerTest extends TestCase
{
    public function testGoldenFixtures(): void
    {
        $fixtures = json_decode((string) file_get_contents(dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/tokens.json'), true);
        $handler = new JwksAccessTokenHandler(
            dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/jwks.json',
            '',
            $fixtures['issuer'],
            $fixtures['audience'],
        );

        self::assertSame('7', $handler->getUserBadgeFrom($fixtures['valid'])->getUserIdentifier());

        foreach (['expired', 'wrong_type', 'missing_type', 'wrong_alg', 'missing_aud', 'wrong_aud'] as $name) {
            try {
                $handler->getUserBadgeFrom($fixtures[$name]);
                self::fail($name.' was accepted');
            } catch (AuthenticationException) {
                self::assertTrue(true);
            }
        }
    }
}
