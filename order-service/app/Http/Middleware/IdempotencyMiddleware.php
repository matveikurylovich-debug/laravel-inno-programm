<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;

class IdempotencyMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! is_string($idempotencyKey) || $idempotencyKey === '') {
            return $next($request);
        }

        $userId = (string) $request->attributes->get('auth_user_id', 'guest');
        $redisKey = "idempotency:order:{$userId}:{$idempotencyKey}";
        $lockKey = "lock:{$redisKey}";

        $cached = Redis::get($redisKey);

        if (is_string($cached)) {
            $stored = json_decode($cached, true);

            return response($stored['body'] ?? '', (int) ($stored['status'] ?? Response::HTTP_OK))
                ->header('Content-Type', 'application/json')
                ->header('X-Idempotent-Replay', 'true');
        }

        $locked = Cache::lock($lockKey, 10);

        if (!$locked->get()) {
            return response()->json([
                'error' => 'Conflict',
                'details' => 'Запрос с этим ключом идемпотентности уже выполняется',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $response = $next($request);

            if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
                Redis::setex($redisKey, 86400, json_encode([
                    'status' => $response->getStatusCode(),
                    'body' => $response->getContent(),
                ], JSON_THROW_ON_ERROR));
            }

            return $response;
        } finally {
            $locked->release();
        }
    }
}
