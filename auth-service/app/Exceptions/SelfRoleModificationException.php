<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Http\HasProblemCode;
use Ecommerce\PlatformKit\Http\ProvidesProblem;
use RuntimeException;

final class SelfRoleModificationException extends RuntimeException implements HasProblemCode, ProvidesProblem
{
    public function __construct()
    {
        parent::__construct(__('Нельзя изменить роль собственной учетной записи.'));
    }

    public function problemStatus(): int
    {
        return 403;
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
        return 'self_role_modification_forbidden';
    }
}
