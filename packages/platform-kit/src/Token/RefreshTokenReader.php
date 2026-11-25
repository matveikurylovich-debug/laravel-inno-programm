<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use stdClass;
use Throwable;

final class RefreshTokenReader
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algorithm = 'HS256',
    ) {
        if (strlen($this->secret) < 32) {
            throw new TokenInfrastructureException('JWT refresh secret must be at least 32 bytes.');
        }
    }

    public function read(string $token): stdClass
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
        } catch (Throwable) {
            throw InvalidTokenException::malformed();
        }

        if (($decoded->type ?? null) !== TokenType::Refresh->value) {
            throw InvalidTokenException::type();
        }

        return $decoded;
    }
}
