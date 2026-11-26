<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$allowedRoles): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasAnyRole(...$allowedRoles)) {
            abort(Response::HTTP_FORBIDDEN, __('Доступ запрещен. Требуются роли: :roles', [
                'roles' => implode(', ', $allowedRoles),
            ]));
        }

        return $next($request);
    }
}
