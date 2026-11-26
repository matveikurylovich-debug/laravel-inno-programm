<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Http;

interface HasProblemCode
{
    public function problemCode(): string;
}
