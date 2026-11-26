<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Config;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

class TokenCookies
{
    public function __construct(
        #[Config('jwt.cookies.access', '__Host-access')]
        private readonly string $accessCookie = '__Host-access',
        #[Config('jwt.cookies.refresh', 'refresh_token')]
        private readonly string $refreshCookie = 'refresh_token',
        #[Config('jwt.ttl.access', 900)]
        private readonly int $accessTtl = 900,
        #[Config('jwt.ttl.refresh', 1209600)]
        private readonly int $refreshTtl = 1209600,
        #[Config('jwt.cookie_secure', true)]
        private readonly bool $cookieSecure = true,
    ) {}

    public function attach(JsonResponse $response, string $access, string $refresh): void
    {
        $response->headers->setCookie($this->access($access));
        $response->headers->setCookie($this->refresh($refresh));
    }

    public function clear(JsonResponse $response): void
    {
        $response->headers->clearCookie($this->accessCookie, '/', null, $this->cookieSecure, true, 'lax');
        $response->headers->clearCookie($this->refreshCookie, '/api/auth', null, $this->cookieSecure, true, 'lax');
    }

    private function access(string $value): Cookie
    {
        return Cookie::create($this->accessCookie)
            ->withValue($value)
            ->withExpires(CarbonImmutable::now()->getTimestamp() + $this->accessTtl)
            ->withPath('/')
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite('lax');
    }

    private function refresh(string $value): Cookie
    {
        return Cookie::create($this->refreshCookie)
            ->withValue($value)
            ->withExpires(CarbonImmutable::now()->getTimestamp() + $this->refreshTtl)
            ->withPath('/api/auth')
            ->withSecure($this->cookieSecure)
            ->withHttpOnly(true)
            ->withSameSite('lax');
    }
}
