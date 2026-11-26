<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Http\HasProblemCode;
use Ecommerce\PlatformKit\Http\ProvidesProblem;
use RuntimeException;

final class LastAdminException extends RuntimeException implements HasProblemCode, ProvidesProblem
{
    public function __construct()
    {
        parent::__construct(__('Нельзя снять роль с последнего администратора.'));
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
     * @return array<string, mixed>
     */
    public function problemErrors(): array
    {
        return [];
    }

    public function problemCode(): string
    {
        return 'last_admin_protected';
    }
}
