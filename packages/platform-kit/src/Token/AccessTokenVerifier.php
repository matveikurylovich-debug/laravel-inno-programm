<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Throwable;
use UnexpectedValueException;

final class AccessTokenVerifier
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algorithm = 'HS256',
    ) {
        if (strlen($this->secret) < 32) {
            throw new TokenInfrastructureException('JWT access secret must be at least 32 bytes.');
        }
    }

    public function verify(string $token): AccessClaims
    {
        if ($token === '') {
            throw InvalidTokenException::missing();
        }

        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (ExpiredException) {
            throw InvalidTokenException::expired();
        } catch (SignatureInvalidException) {
            throw InvalidTokenException::signature();
        } catch (UnexpectedValueException) {
            throw InvalidTokenException::malformed();
        } catch (InvalidTokenException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw InvalidTokenException::malformed();
        }

        return AccessClaims::fromPayload($decoded);
    }
}
