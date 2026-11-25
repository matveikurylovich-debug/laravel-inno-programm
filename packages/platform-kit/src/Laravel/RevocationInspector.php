<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Laravel;

use Ecommerce\PlatformKit\Token\AccessClaims;

interface RevocationInspector
{
    public function assertActive(AccessClaims $claims, string $token): void;
}
