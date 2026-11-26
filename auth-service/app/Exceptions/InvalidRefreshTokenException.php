<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Http\HasProblemCode;
use Ecommerce\PlatformKit\Http\ProvidesProblem;
use RuntimeException;

final class InvalidRefreshTokenException extends RuntimeException implements HasProblemCode, ProvidesProblem
{
    public function __construct(
        private readonly int $status = 401,
        ?string $message = null,
        private readonly string $codeName = 'invalid_refresh_token',
    ) {
        parent::__construct($message ?? __('Недействительный или отозванный refresh-токен'));
    }

    public function problemStatus(): int
    {
        return $this->status;
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
        return $this->codeName;
    }
}
