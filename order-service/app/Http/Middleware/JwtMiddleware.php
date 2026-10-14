<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        $token = $request->bearerToken();

        if (! is_string($token) || $token === '') {
            return $this->error('Unauthorized', 'Токен авторизации отсутствует', Response::HTTP_UNAUTHORIZED);
        }

        try {
            $secret = config('jwt.access_secret', (string) config('JWT_ACCESS_SECRET'));
            $algo = (string) config('jwt.algo', 'HS256');

            if (! is_string($secret) || $secret === '') {
                throw new Exception('JWT secret is not configured');
            }

            $decoded = JWT::decode($token, new Key($secret, $algo));

            if (($decoded->type ?? null) !== 'access') {
                throw new Exception('Некорректный тип токена');
            }

            if (! isset($decoded->sub)) {
                throw new Exception('В токене нет идентификатора пользователя');
            }
        } catch (ExpiredException) {
            return $this->error('Unauthorized', 'Срок действия токена истек', Response::HTTP_UNAUTHORIZED);
        } catch (SignatureInvalidException) {
            return $this->error('Unauthorized', 'Недействительная цифровая подпись токена', Response::HTTP_UNAUTHORIZED);
        } catch (Exception $e) {
            return $this->error('Unauthorized', $e->getMessage(), Response::HTTP_UNAUTHORIZED);
        }

        $role = isset($decoded->role) ? (string) $decoded->role : null;

        if ($requiredRole !== null && $role !== $requiredRole) {
            return $this->error('Forbidden', 'Недостаточно прав для выполнения действия', Response::HTTP_FORBIDDEN);
        }

        $request->attributes->set('auth_user_id', $decoded->sub);
        $request->attributes->set('auth_email', isset($decoded->email) ? (string) $decoded->email : null);
        $request->attributes->set('auth_role', $role);

        return $next($request);
    }

    private function error(string $error, string $details, int $status): Response
    {
        return response()->json([
            'error' => $error,
            'details' => $details,
        ], $status);
    }
}
