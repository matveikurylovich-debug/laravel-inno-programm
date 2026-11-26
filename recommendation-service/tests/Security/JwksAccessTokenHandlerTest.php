<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Security\JwksAccessTokenHandler;
use App\Security\JwtUser;
use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

final class JwksAccessTokenHandlerTest extends TestCase
{
    public function testGoldenFixtures(): void
    {
        $rawFixtures = (string) file_get_contents(\dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/tokens.json');
        /** @var array<string, mixed> $fixtures */
        $fixtures = json_decode($rawFixtures, true, 512, \JSON_THROW_ON_ERROR);

        $issuer = \is_string($fixtures['issuer'] ?? null) ? $fixtures['issuer'] : '';
        $audience = \is_string($fixtures['audience'] ?? null) ? $fixtures['audience'] : '';
        $validToken = \is_string($fixtures['valid'] ?? null) ? $fixtures['valid'] : '';

        $handler = new JwksAccessTokenHandler(
            \dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/jwks.json',
            '',
            $issuer,
            $audience,
        );

        $badge = $handler->getUserBadgeFrom($validToken);
        $this->assertSame('7', $badge->getUserIdentifier());

        foreach (['expired', 'wrong_type', 'missing_type', 'wrong_alg', 'missing_aud', 'wrong_aud'] as $name) {
            $token = \is_string($fixtures[$name] ?? null) ? $fixtures[$name] : '';
            try {
                $handler->getUserBadgeFrom($token);
                self::fail($name.' was accepted');
            } catch (AuthenticationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testNormalizesUserRolesFromValidSignedToken(): void
    {
        $privateKey = (string) file_get_contents(\dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/access-private.pem');
        $signer = new AccessTokenSigner($privateKey, 'test-1', 'http://auth-app', 'ecommerce');
        $token = $signer->issue(42, ['admin', 'ROLE_ANALYST']);

        $handler = new JwksAccessTokenHandler(
            \dirname(__DIR__, 3).'/packages/platform-kit/tests/fixtures/jwks.json',
            '',
            'http://auth-app',
            'ecommerce',
        );

        $user = $handler->getUserBadgeFrom($token)->getUser();
        $this->assertInstanceOf(JwtUser::class, $user);
        $this->assertSame(42, $user->getId());
        $this->assertSame(['ROLE_ADMIN', 'ROLE_ANALYST', 'ROLE_USER'], $user->getRoles());
    }

    public function testRejectsMissingJwksConfiguration(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new JwksAccessTokenHandler('', '', 'http://auth-app', 'ecommerce');
    }
}
