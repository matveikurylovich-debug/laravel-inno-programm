<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use Closure;
use Ecommerce\PlatformKit\Laravel\JwtUser;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Проверка ролей пользователя (Admin / Analyst).
     *
     * @param  string  ...$allowedRoles  Список ролей из маршрута (например, role:admin,analyst)
     */
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        if (empty($allowedRoles)) {
            $allowedRoles = [RoleName::Admin->value, RoleName::Analyst->value];
        }

        $user = $request->user();
        $userRoles = $user instanceof JwtUser ? $user->roles() : [];

        // Нормализация к нижнему регистру для нечувствительности к регистру (Admin vs admin, etc.)
        $normalizedUserRoles = array_map(fn ($r) => strtolower(trim((string) $r)), $userRoles);
        $normalizedAllowedRoles = array_map(fn ($r) => strtolower(trim((string) $r)), $allowedRoles);

        $hasAccess = ! empty(array_intersect($normalizedUserRoles, $normalizedAllowedRoles));

        if (! $hasAccess) {
            return response()->json([
                'error' => 'Доступ запрещен. Требуются роли: '.implode(', ', $normalizedAllowedRoles),
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
