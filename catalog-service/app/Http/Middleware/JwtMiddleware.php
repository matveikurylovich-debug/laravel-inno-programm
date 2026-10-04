<?php

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
     * Обработка входящего запроса.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string|null  $requiredRole Ожидаемая роль (например, 'admin')
     */
    public function handle(Request $request, Closure $next, ?string $requiredRole = null): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'success' => false,
                'message' => 'Токен авторизации отсутствует',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $secret = config('jwt.secret') ?? env('JWT_ACCESS_SECRET');
            $algo = config('jwt.algo', 'HS256');

            if (! is_string($secret) || $secret === '') {
                throw new Exception('JWT secret is not configured');
            }

            // Расшифровка и проверка криптографической подписи
            $decoded = JWT::decode($token, new Key($secret, $algo));

            // Access-токен auth-service всегда содержит type=access.
            // Refresh подписан другим секретом и сюда не проходит.
            if (isset($decoded->type) && $decoded->type !== 'access') {
                throw new Exception('Некорректный тип токена');
            }
        } catch (ExpiredException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Срок действия токена истек',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (SignatureInvalidException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Недействительная цифровая подпись токена',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Некорректный токен: '.$e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Проверка прав доступа по роли (если передана в маршруте)
        if ($requiredRole && (! isset($decoded->role) || $decoded->role !== $requiredRole)) {
            return response()->json([
                'success' => false,
                'message' => 'Недостаточно прав для выполнения действия',
            ], Response::HTTP_FORBIDDEN);
        }

        // Пробрасываем декодированные данные токена дальше в запрос
        $request->attributes->set('jwt_payload', $decoded);
        $request->attributes->set('auth_user_id', $decoded->sub ?? null);
        $request->attributes->set('auth_role', $decoded->role ?? null);
        $request->attributes->set('auth_store_id', $decoded->store_id ?? null);

        return $next($request);
    }
}
