<?php

namespace App\Http\Middleware;

use Closure;
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
        $payload = $request->attributes->get('jwt_payload');

        // Если роли не переданы в маршруте явно, по умолчанию разрешаем Admin и Analyst
        if (empty($allowedRoles)) {
            $allowedRoles = ['admin', 'analyst'];
        }

        // Извлекаем роли из запроса или payload токена
        $userRoles = $request->attributes->get('jwt_roles');
        if ($userRoles === null) {
            if (isset($payload->roles)) {
                $userRoles = is_array($payload->roles) ? $payload->roles : (array) $payload->roles;
            } elseif (isset($payload->role)) {
                $userRoles = is_array($payload->role) ? $payload->role : [$payload->role];
            } else {
                $userRoles = [];
            }
        }

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
