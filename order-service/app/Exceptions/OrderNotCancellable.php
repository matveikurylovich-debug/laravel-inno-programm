<?php

declare(strict_types=1);

namespace App\Exceptions;

use Ecommerce\PlatformKit\Http\ProvidesProblem;
use RuntimeException;

class OrderNotCancellable extends RuntimeException implements ProvidesProblem
{
    public function problemStatus(): int
    {
        return 409;
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
}
