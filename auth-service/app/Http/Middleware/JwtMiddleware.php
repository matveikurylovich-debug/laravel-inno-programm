<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\JwtService;
use Closure;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JwtMiddleware
{
    public function __construct(
        protected JwtService $jwtService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['error' => 'Заголовок Authorization: Bearer отсутствует'], Response::HTTP_UNAUTHORIZED);
        }

        try {
        
            $payload = $this->jwtService->decodeAccessToken($token);

            $user = User::find($payload->sub);
            if (! $user) {
                return response()->json(['error' => 'Пользователь не найден'], Response::HTTP_UNAUTHORIZED);
            }

            $request->setUserResolver(fn () => $user);

        } catch (ExpiredException) {
            return response()->json(['error' => 'Срок действия access-токена истек'], Response::HTTP_UNAUTHORIZED);
        } catch (SignatureInvalidException) {
            return response()->json(['error' => 'Неверная подпись токена'], Response::HTTP_UNAUTHORIZED);
        } catch (Throwable $e) {
            return response()->json(['error' => $e->getMessage() ?: 'Доступ запрещен'], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}