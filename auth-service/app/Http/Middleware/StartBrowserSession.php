<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\TokenCookies;
use App\Support\FrontendUrl;
use Closure;
use Ecommerce\PlatformKit\Laravel\ProblemJson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StartBrowserSession
{
    public function __construct(private readonly TokenCookies $cookies) {}

    public function handle(Request $request, Closure $next): Response
    {
        $accessCookie = (string) config('jwt.cookies.access');
        $refreshCookie = (string) config('jwt.cookies.refresh');
        $browser = $request->header('X-Browser-Session') === '1' || $request->hasCookie($accessCookie) || $request->hasCookie($refreshCookie);

        if ($browser && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            $origin = $request->header('Origin');
            if (! is_string($origin) || $origin === '' || ! FrontendUrl::isAllowed($request, $origin)) {
                return ProblemJson::make(403, __('Недопустимый источник запроса.'), code: 'invalid_origin');
            }
        }

        $accessValue = $request->cookie($accessCookie);
        if (! $request->bearerToken() && is_string($accessValue) && $accessValue !== '') {
            $request->headers->set('Authorization', 'Bearer '.$accessValue);
        }

        $refreshValue = $request->cookie($refreshCookie);
        if ($browser && is_string($refreshValue) && $refreshValue !== '' && $request->is('api/v1/refresh', 'api/v1/logout')) {
            $request->merge(['refresh_token' => $refreshValue]);
        }

        $response = $next($request);
        if ($browser && $response instanceof JsonResponse) {
            $data = $response->getData(true);
            if (is_array($data) && isset($data['access_token'], $data['refresh_token']) && is_string($data['access_token']) && is_string($data['refresh_token'])) {
                $this->cookies->attach($response, $data['access_token'], $data['refresh_token']);
                unset($data['access_token'], $data['refresh_token']);
                $response->setData($data);
            }
            if ($request->is('api/v1/logout')) {
                $this->cookies->clear($response);
            }
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
