<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Http\HasProblemCode;
use Ecommerce\PlatformKit\Http\ProvidesProblem;
use RuntimeException;

final class InvalidCredentialsException extends RuntimeException implements HasProblemCode, ProvidesProblem
{
    public function __construct(?string $message = null)
    {
        parent::__construct($message ?? __('auth.failed'));
    }

    public function problemStatus(): int
    {
        return 401;
    }

    public function problemDetail(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, mixed>
     */
    public function problemErrors(): array
    {
        return [];
    }

    public function problemCode(): string
    {
        return 'invalid_credentials';
    }
}
