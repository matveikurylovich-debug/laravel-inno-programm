<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Ecommerce\PlatformKit\Laravel\ProblemJson;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureIdempotentRequest
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return ProblemJson::make(
                Response::HTTP_BAD_REQUEST,
                'Заголовок Idempotency-Key обязателен для создания заказа',
            );
        }

        $userId = (string) ($request->user()?->getAuthIdentifier() ?? $request->attributes->get('auth_user_id', 'guest'));
        $cacheKey = "idempotency:order:{$userId}:{$idempotencyKey}";
        $fingerprint = hash('sha256', $request->getContent());

        try {
            return Cache::lock("lock:{$cacheKey}", 30)->block(5, function () use ($cacheKey, $fingerprint, $request, $next): Response {
                $stored = Cache::get($cacheKey);

                if (is_array($stored)) {
                    if (($stored['fingerprint'] ?? null) !== $fingerprint) {
                        return ProblemJson::make(
                            Response::HTTP_UNPROCESSABLE_ENTITY,
                            'Ключ Idempotency-Key уже использован с другим телом запроса',
                        );
                    }

                    return response((string) ($stored['body'] ?? ''), (int) ($stored['status'] ?? Response::HTTP_OK))
                        ->header('Content-Type', 'application/json')
                        ->header('X-Idempotent-Replay', 'true');
                }

                $response = $next($request);

                if ($response->isSuccessful()) {
                    Cache::put($cacheKey, [
                        'status' => $response->getStatusCode(),
                        'body' => $response->getContent(),
                        'fingerprint' => $fingerprint,
                    ], now()->addDay());
                }

                return $response;
            });
        } catch (LockTimeoutException) {
            return ProblemJson::make(
                Response::HTTP_CONFLICT,
                'Запрос с этим ключом идемпотентности уже выполняется',
            );
        }
    }
}
