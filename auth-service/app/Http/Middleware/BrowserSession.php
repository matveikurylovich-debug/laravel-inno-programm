<?php

namespace App\Http\Middleware;

use App\Services\TokenCookies;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BrowserSession
{
    public function __construct(private readonly TokenCookies $cookies) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accessCookie = (string) config('jwt.cookies.access');
        $refreshCookie = (string) config('jwt.cookies.refresh');
        $browser = $request->header('X-Browser-Session') === '1' || $request->hasCookie($accessCookie) || $request->hasCookie($refreshCookie);
        if ($browser && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $origin = $request->header('Origin');
            $expected = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            if (! $origin || ! hash_equals($expected, rtrim($origin, '/'))) {
                return response()->json(['error' => 'Invalid request origin'], 403);
            }
        }
        if (! $request->bearerToken() && $request->cookie($accessCookie)) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie($accessCookie));
        }
        if ($browser && $request->cookie($refreshCookie) && $request->is('api/refresh', 'api/logout')) {
            $request->merge(['refresh_token' => $request->cookie($refreshCookie)]);
        }
        $response = $next($request);
        if ($browser && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (isset($data['access_token'], $data['refresh_token'])) {
                $this->cookies->attach($response, (string) $data['access_token'], (string) $data['refresh_token']);
                unset($data['access_token'], $data['refresh_token']);
                $response->setData($data);
            }
            if ($request->is('api/logout')) {
                $this->cookies->clear($response);
            }
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
