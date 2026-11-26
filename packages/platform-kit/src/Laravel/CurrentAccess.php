<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Laravel;

use Ecommerce\PlatformKit\Token\AccessClaims;
use Illuminate\Support\Facades\Auth;

final class CurrentAccess
{
    public function claims(): ?AccessClaims
    {
        $user = Auth::guard('api')->user();

        return $user instanceof JwtUser ? $user->claims : null;
    }
}
