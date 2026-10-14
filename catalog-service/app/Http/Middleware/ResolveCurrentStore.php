<?php

namespace App\Http\Middleware;

use App\Support\CurrentStore;
use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ResolveCurrentStore
{
    public function __construct(private readonly CurrentStore $currentStore) {}

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
        $fromRequest = $this->intOrNull($request->attributes->get('auth_store_id'));

        if ($fromRequest !== null) {
            return $fromRequest;
        }

        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return null;
        }

        $secret = config('jwt.secret') ?? env('JWT_ACCESS_SECRET');

        if (! is_string($secret) || $secret === '') {
            return null;
        }

        try {
            $decoded = JWT::decode($token, new Key($secret, (string) config('jwt.algo', 'HS256')));
        } catch (Throwable) {
            return null;
        }

        return $this->intOrNull($decoded->store_id ?? null);
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
