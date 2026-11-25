<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

use RuntimeException;

final class InvalidTokenException extends RuntimeException
{
    public static function missing(): self
    {
        return new self('Access token is missing.');
    }

    public static function expired(): self
    {
        return new self('Access token is expired.');
    }

    public static function signature(): self
    {
        return new self('Access token signature is invalid.');
    }

    public static function type(): self
    {
        return new self('Access token type is invalid.');
    }

    public static function malformed(): self
    {
        return new self('Access token is malformed.');
    }

    public static function revoked(): self
    {
        return new self('Access token is revoked.');
    }
}
