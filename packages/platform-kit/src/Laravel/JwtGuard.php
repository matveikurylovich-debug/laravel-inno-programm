<?php

declare(strict_types=1);

namespace Ecommerce\PlatformKit\Laravel;

use Illuminate\Auth\RequestGuard;
use Illuminate\Http\Request;

/**
 * RequestGuard keeps the resolved user after the request is replaced.
 * A new HTTP request in the same process must authenticate again.
 */
final class JwtGuard extends RequestGuard
{
    public function setRequest(Request $request)
    {
        $this->user = null;

        return parent::setRequest($request);
    }
}
