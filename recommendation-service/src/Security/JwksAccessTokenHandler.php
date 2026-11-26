<?php

declare(strict_types=1);

namespace App\Security;

use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\JwksClient;
use InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final readonly class JwksAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        #[Autowire(env: 'JWT_JWKS_PATH')]
        private string $jwksPath,
        #[Autowire(env: 'JWT_JWKS_URL')]
        private string $jwksUrl,
        #[Autowire(env: 'JWT_ISSUER')]
        private string $issuer,
        #[Autowire(env: 'JWT_AUDIENCE')]
        private string $audience,
    ) {
        if (('' === $this->jwksPath || !is_file($this->jwksPath)) && '' === $this->jwksUrl) {
            throw new InvalidArgumentException('JWT_JWKS_URL or JWT_JWKS_PATH is required.');
        }
        if ('' === $this->issuer || '' === $this->audience) {
            throw new InvalidArgumentException('JWT issuer and audience must be configured.');
        }
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        try {
            $claims = $this->verifier()->verify($accessToken);
        } catch (InvalidTokenException $exception) {
            throw new CustomUserMessageAuthenticationException('Требуется авторизация.', previous: $exception);
        }

        $roles = [];
        foreach ($claims->roles as $role) {
            $roles[] = str_starts_with($role, 'ROLE_') ? $role : 'ROLE_'.strtoupper($role);
        }

        return new UserBadge((string) $claims->userId, static fn (): JwtUser => new JwtUser($claims->userId, '', $roles));
    }

    private function verifier(): AccessTokenVerifier
    {
        if ('' !== $this->jwksPath && is_file($this->jwksPath)) {
            return AccessTokenVerifier::fromJwks((string) file_get_contents($this->jwksPath), $this->issuer, $this->audience);
        }

        $json = new JwksClient($this->jwksUrl, sys_get_temp_dir().'/recommendation-jwks.json')->json();

        return AccessTokenVerifier::fromJwks($json, $this->issuer, $this->audience);
    }
}
