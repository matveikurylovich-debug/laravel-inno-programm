<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Contracts\RefreshTokenStore;
use Ecommerce\PlatformKit\Laravel\RevocationInspector;
use Ecommerce\PlatformKit\Token\AccessClaims;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Throwable;

final readonly class RedisRevocationInspector implements RevocationInspector
{
    public function __construct(
        private RefreshTokenStore $store,
    ) {}

    public function assertActive(AccessClaims $claims, string $token): void
    {
        try {
            if ($this->store->isAccessBlacklisted($claims->jti)) {
                throw InvalidTokenException::revoked();
            }

            $notBefore = $this->store->getNotBefore($claims->userId);
            if ($notBefore !== null && $claims->iat <= $notBefore) {
                throw InvalidTokenException::revoked();
            }
        } catch (InvalidTokenException|TokenInfrastructureException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TokenInfrastructureException('Revocation store is unavailable.', previous: $exception);
        }
    }
}
