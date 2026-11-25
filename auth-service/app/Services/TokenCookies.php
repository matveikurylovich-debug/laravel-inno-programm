<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Cookie;

class TokenCookies
{
    public function attach(JsonResponse $response, string $access, string $refresh): void
    {
        $response->headers->setCookie($this->access($access));
        $response->headers->setCookie($this->refresh($refresh));
    }

    public function clear(JsonResponse $response): void
    {
        $secure = $this->secure();
        $response->headers->clearCookie((string) config('jwt.cookies.access'), '/', null, $secure, true, 'lax');
        $response->headers->clearCookie((string) config('jwt.cookies.refresh'), '/api/auth', null, $secure, true, 'lax');
    }

    private function access(string $value): Cookie
    {
        return Cookie::create((string) config('jwt.cookies.access'))
            ->withValue($value)
            ->withExpires(time() + (int) config('jwt.ttl.access'))
            ->withPath('/')
            ->withSecure($this->secure())
            ->withHttpOnly(true)
            ->withSameSite('lax');
    }

    private function refresh(string $value): Cookie
    {
        return Cookie::create((string) config('jwt.cookies.refresh'))
            ->withValue($value)
            ->withExpires(time() + (int) config('jwt.ttl.refresh'))
            ->withPath('/api/auth')
            ->withSecure($this->secure())
            ->withHttpOnly(true)
            ->withSameSite('lax');
    }

    private function secure(): bool
    {
        return (bool) config('jwt.cookie_secure', true);
    }
}
