<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Token;

use Symfony\Component\HttpKernel\Exception\HttpException;

final class InvalidTokenException extends HttpException
{
    public function __construct(string $message)
    {
        parent::__construct(401, $message);
    }

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

    public static function audience(): self
    {
        return new self('Access token audience is invalid.');
    }

    public static function issuer(): self
    {
        return new self('Access token issuer is invalid.');
    }
}
