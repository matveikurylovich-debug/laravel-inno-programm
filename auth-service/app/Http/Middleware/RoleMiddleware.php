<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasAnyRole(...$allowedRoles)) {
            return response()->json([
                'error' => 'Доступ запрещен. Требуются роли: '.implode(', ', $allowedRoles),
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
