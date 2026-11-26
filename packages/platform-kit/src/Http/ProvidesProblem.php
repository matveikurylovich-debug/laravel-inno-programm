<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Http;

interface ProvidesProblem
{
    public function problemStatus(): int;

    public function problemDetail(): string;

    /**
     * @return array<string, mixed>
     */
    public function problemErrors(): array;
}
