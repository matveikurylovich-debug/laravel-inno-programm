<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use OpenApi\Attributes as OA;
use Throwable;

class GatewayController extends Controller
{
    /**
     * Проксирование запросов к Catalog Service.
     */
    #[OA\Get(
        path: '/api/catalog/{path}',
        summary: 'Gateway: catalog (GET)',
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream catalog-service'),
            new OA\Response(response: 502, description: 'Upstream недоступен'),
        ]
    )]
    #[OA\Post(
        path: '/api/catalog/{path}',
        summary: 'Gateway: catalog (POST, admin)',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream catalog-service'),
            new OA\Response(response: 403, description: 'Только admin'),
        ]
    )]
    #[OA\Put(
        path: '/api/catalog/{path}',
        summary: 'Gateway: catalog (PUT, admin)',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream catalog-service'),
        ]
    )]
    #[OA\Patch(
        path: '/api/catalog/{path}',
        summary: 'Gateway: catalog (PATCH, admin)',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream catalog-service'),
        ]
    )]
    #[OA\Delete(
        path: '/api/catalog/{path}',
        summary: 'Gateway: catalog (DELETE, admin)',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream catalog-service'),
        ]
    )]
    public function catalog(Request $request, ?string $path = ''): Response
    {
        // Мутирующие методы для каталога доступны только администраторам
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $user = $request->user();
            if ($user === null || ! $user->hasRole('admin')) {
                return response([
                    'error' => 'Недостаточно прав. Действие доступно только роли admin.'
                ], 403, ['Content-Type' => 'application/json']);
            }
        }

        return $this->proxy($request, $this->target(config('services.gateway.catalog'), $path));
    }

    /**
     * Проксирование запросов к Order Service.
     */
    #[OA\Get(
        path: '/api/orders/{path}',
        summary: 'Gateway: orders',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream order-service'),
            new OA\Response(response: 401, description: 'Не авторизован'),
        ]
    )]
    #[OA\Post(
        path: '/api/orders/{path}',
        summary: 'Gateway: orders (POST)',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream order-service'),
        ]
    )]
    #[OA\Delete(
        path: '/api/orders/{path}',
        summary: 'Gateway: orders (DELETE)',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Удалено'),
        ]
    )]
    public function orders(Request $request, ?string $path = ''): Response
    {
        return $this->proxy($request, $this->target(config('services.gateway.order'), 'orders/'.ltrim((string) $path, '/')));
    }

    /**
     * Проксирование запросов к Search Service.
     */
    #[OA\Get(
        path: '/api/search',
        summary: 'Gateway: search',
        tags: ['Gateway'],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream search-service'),
        ]
    )]
    public function search(Request $request): Response
    {
        return $this->proxy($request, $this->target(config('services.gateway.search'), 'search'));
    }

    #[OA\Get(
        path: '/api/notifications/{path}',
        summary: 'Gateway: notifications (GET)',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        description: 'Роли admin, analyst',
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream notification-service'),
        ]
    )]
    #[OA\Post(
        path: '/api/notifications/{path}',
        summary: 'Gateway: notifications (POST, admin)',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream notification-service'),
        ]
    )]
    public function notifications(Request $request, ?string $path = ''): Response
    {
        return $this->proxy($request, $this->target(config('services.gateway.notifications'), 'notifications/'.ltrim((string) $path, '/')));
    }

    /**
     * Проксирование запросов к Analytics.
     */
    #[OA\Get(
        path: '/api/analytics/{path}',
        summary: 'Gateway: analytics',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        description: 'Роли admin, analyst',
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream analytics'),
        ]
    )]
    public function analytics(Request $request, ?string $path = ''): Response
    {
        return $this->proxy($request, $this->target(config('services.gateway.analytics'), $path));
    }

    #[OA\Get(
        path: '/api/recommendations/{path}',
        summary: 'Gateway: recommendations',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream recommendation-service'),
        ]
    )]
    #[OA\Post(
        path: '/api/recommendations/{path}',
        summary: 'Gateway: recommendations (POST)',
        security: [['bearerAuth' => []]],
        tags: ['Gateway'],
        parameters: [
            new OA\Parameter(name: 'path', in: 'path', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ответ upstream recommendation-service'),
        ]
    )]
    #[OA\Get(
        path: '/api/recommendations/health',
        summary: 'Gateway: recommendations health (public)',
        tags: ['Gateway'],
        responses: [
            new OA\Response(response: 200, description: 'OK'),
        ]
    )]
    public function recommendations(Request $request, ?string $path = ''): Response
    {
        $base = config('services.gateway.recommendations');
        $cleanPath = ltrim((string) $path, '/');

        if (! str_ends_with(rtrim((string) $base, '/'), 'recommendations')) {
            $suffix = $cleanPath === '' ? 'recommendations' : 'recommendations/'.$cleanPath;
        } else {
            $suffix = $cleanPath;
        }

        return $this->proxy($request, $this->target($base, $suffix));
    }

    private function target(?string $baseUrl, ?string $path): string
    {
        $base = rtrim((string) $baseUrl, '/');
        $suffix = ltrim((string) $path, '/');

        return $suffix === '' ? $base : $base.'/'.$suffix;
    }

    /**
     * Базовый метод ретрансляции HTTP-запроса.
     */
    private function proxy(Request $request, string $targetUrl): Response
    {
        try {
            // Формируем набор заголовков для пересылки
            $headers = array_filter([
                'Authorization'   => $request->header('Authorization'),
                'Idempotency-Key' => $request->header('Idempotency-Key'),
                'Content-Type'    => $request->header('Content-Type', 'application/json'),
                'Accept'          => $request->header('Accept', 'application/json'),
                'X-Forwarded-For' => $request->ip(),
            ]);

            // Выполняем внутренний сетевой запрос
            $upstreamResponse = Http::withoutVerifying()
                ->withoutRedirecting()
                ->timeout(15)
                ->withHeaders($headers)
                ->send($request->method(), $targetUrl, [
                    'query' => $request->query(),
                    'body'  => $request->getContent(),
                ]);

            return response(
                $upstreamResponse->body(),
                $upstreamResponse->status(),
                [
                    'Content-Type'        => $upstreamResponse->header('Content-Type') ?? 'application/json',
                    'X-Gateway-Proxied'   => 'true',
                ]
            );
        } catch (Throwable $e) {
            return response([
                'error'   => 'Upstream Service Unavailable',
                'details' => $e->getMessage(),
                'target'  => $targetUrl,
            ], 502, ['Content-Type' => 'application/json']);
        }
    }
}
