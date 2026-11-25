<?php

namespace App\Http\Middleware;

use App\Support\CurrentStore;
use Closure;
use Ecommerce\PlatformKit\Laravel\CurrentAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveCurrentStore
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly CurrentAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeStore = $this->intOrNull($request->route('storeId'));
        $tokenStore = $this->storeIdFromToken($request);

        if ($tokenStore !== null && $routeStore !== null && $tokenStore !== $routeStore) {
            return response()->json([
                'success' => false,
                'message' => 'Нет доступа к этому магазину',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->currentStore->set($tokenStore ?? $routeStore);

        return $next($request);
    }

    private function storeIdFromToken(Request $request): ?int
    {
        return $this->access->claims()?->storeId;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return (int) $value;
    }
}
