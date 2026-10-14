<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class GatewayController extends Controller
{
    /**
     * Проксирование запросов к Catalog Service.
     */
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
    public function orders(Request $request, ?string $path = ''): Response
    {
        return $this->proxy($request, $this->target(config('services.gateway.order'), 'orders/'.ltrim((string) $path, '/')));
    }

    /**
     * Проксирование запросов к Notification Service.
     */
    public function search(Request $request): Response
    {
        return $this->proxy($request, $this->target(config('services.gateway.search'), 'search'));
    }

    public function notifications(Request $request, ?string $path = ''): Response
    {
        return $this->proxy($request, $this->target(config('services.gateway.notifications'), 'notifications/'.ltrim((string) $path, '/')));
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