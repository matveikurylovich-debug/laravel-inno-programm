<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Http\HasProblemCode;
use Ecommerce\PlatformKit\Http\ProvidesProblem;
use RuntimeException;

final class TwoFactorRateLimitException extends RuntimeException implements HasProblemCode, ProvidesProblem
{
    public function __construct(
        string $message,
        private readonly string $codeName,
    ) {
        parent::__construct($message);
    }

    public static function tooManyAttempts(): self
    {
        return new self(
            message: __('Превышено число попыток. Войдите заново.'),
            codeName: 'two_factor_locked',
        );
    }

    public static function tooManyResends(): self
    {
        return new self(
            message: __('Слишком много повторных отправок. Войдите заново.'),
            codeName: 'two_factor_resend_limit',
        );
    }

    public function problemStatus(): int
    {
        return 429;
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
