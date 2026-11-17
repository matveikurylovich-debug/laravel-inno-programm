<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class BrowserSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $browser = $request->header('X-Browser-Session') === '1' || $request->hasCookie('access_token') || $request->hasCookie('refresh_token');
        if ($browser && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $origin = $request->header('Origin');
            $expected = rtrim((string) config('app.frontend_url', config('app.url')), '/');
            if (! $origin || ! hash_equals($expected, rtrim($origin, '/'))) {
                return response()->json(['error' => 'Invalid request origin'], 403);
            }
        }
        if (! $request->bearerToken() && $request->cookie('access_token')) {
            $request->headers->set('Authorization', 'Bearer '.$request->cookie('access_token'));
        }
        if ($browser && $request->cookie('refresh_token') && $request->is('api/refresh', 'api/logout')) {
            $request->merge(['refresh_token' => $request->cookie('refresh_token')]);
        }
        $response = $next($request);
        if ($browser && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (isset($data['access_token'], $data['refresh_token'])) {
                foreach (['access_token' => (int) config('jwt.ttl.access'), 'refresh_token' => (int) config('jwt.ttl.refresh')] as $name => $ttl) {
                    $response->headers->setCookie(Cookie::create($name)->withValue($data[$name])->withExpires(time() + $ttl)
                        ->withPath('/')->withSecure((bool) config('jwt.cookie_secure', true))->withHttpOnly(true)->withSameSite('lax'));
                    unset($data[$name]);
                }
                $response->setData($data);
            }
            if ($request->is('api/logout') || ($request->is('api/refresh') && $response->getStatusCode() === 401)) {
                foreach (['access_token', 'refresh_token'] as $name) {
                    $response->headers->clearCookie($name, '/', null, (bool) config('jwt.cookie_secure', true), true, 'lax');
                }
            }
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
