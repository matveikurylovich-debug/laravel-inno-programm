<?php

namespace App\Auth;

use Ecommerce\PlatformKit\Laravel\RevocationInspector;
use Ecommerce\PlatformKit\Token\AccessClaims;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Illuminate\Support\Facades\Redis;
use Throwable;

final class RedisRevocationInspector implements RevocationInspector
{
    public function assertActive(AccessClaims $claims, string $token): void
    {
        try {
            if (Redis::exists('jwt:blacklist:jti:'.$claims->jti)) {
                throw InvalidTokenException::revoked();
            }

            $notBefore = Redis::get('jwt:user:'.$claims->userId.':nbf');
            if (is_string($notBefore) && $notBefore !== '' && $claims->iat <= (int) $notBefore) {
                throw InvalidTokenException::revoked();
            }
        } catch (InvalidTokenException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new TokenInfrastructureException('Revocation store is unavailable.', previous: $exception);
        }
    }
}
