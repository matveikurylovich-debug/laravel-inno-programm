<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Http\HasProblemCode;
use Ecommerce\PlatformKit\Http\ProvidesProblem;
use RuntimeException;

final class InvalidTwoFactorChallengeException extends RuntimeException implements HasProblemCode, ProvidesProblem
{
    public function __construct(
        private readonly string $field,
        string $message,
        private readonly string $codeName = 'invalid_two_factor_code',
    ) {
        parent::__construct($message);
    }

    public static function invalidCode(): self
    {
        return new self(
            field: 'code',
            message: __('Неверный или просроченный код безопасности.'),
            codeName: 'invalid_two_factor_code',
        );
    }

    public static function missingChallenge(): self
    {
        return new self(
            field: 'challenge_id',
            message: __('Сначала войдите по паролю.'),
            codeName: 'two_factor_challenge_required',
        );
    }

    public function problemStatus(): int
    {
        return 422;
    }

    public function problemDetail(): string
    {
        return $this->getMessage();
    }

    /**
     * @return array<string, list<string>>
     */
    public function problemErrors(): array
    {
        return [
            $this->field => [$this->getMessage()],
        ];
    }

    public function problemCode(): string
    {
        return $this->codeName;
    }
}
