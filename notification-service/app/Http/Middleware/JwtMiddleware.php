<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JwtMiddleware
{
    /**
     * Валидация JWT Bearer токена.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'Заголовок Authorization: Bearer отсутствует',
            ], Response::HTTP_UNAUTHORIZED);
        }

        try {
            $secret = (string) config('jwt.keys.access');
            $algo = (string) config('jwt.algo', 'HS256');

            if (empty($secret)) {
                return response()->json([
                    'error' => 'Секретный ключ JWT не настроен на сервере',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $payload = JWT::decode($token, new Key($secret, $algo));

            if (($payload->type ?? null) !== 'access') {
                return response()->json([
                    'error' => 'Некорректный тип токена. Ожидается access-токен',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $roles = [];
            if (isset($payload->roles)) {
                $roles = is_array($payload->roles) ? $payload->roles : (array) $payload->roles;
            } elseif (isset($payload->role)) {
                $roles = is_array($payload->role) ? $payload->role : [$payload->role];
            }

            $request->attributes->set('jwt_payload', $payload);
            $request->attributes->set('jwt_user_id', $payload->sub ?? null);
            $request->attributes->set('jwt_roles', $roles);

            $request->setUserResolver(fn () => (object) [
                'id' => $payload->sub ?? null,
                'roles' => $roles,
            ]);

        } catch (ExpiredException) {
            return response()->json([
                'error' => 'Срок действия access-токена истек',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (SignatureInvalidException) {
            return response()->json([
                'error' => 'Неверная подпись токена',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (BeforeValidException) {
            return response()->json([
                'error' => 'Токен еще не активен',
            ], Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            return response()->json([
                'error' => 'Недействительный токен: '.$e->getMessage(),
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
