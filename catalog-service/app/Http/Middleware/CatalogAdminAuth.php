<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CatalogAdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin', 'admin/*', 'catalog-admin', 'catalog-admin/*', 'livewire/*')) {
            return $next($request);
        }
        $token = $request->bearerToken() ?: $request->cookie('access_token');
        if (! $token) {
            abort(401);
        }
        try {
            $response = Http::timeout(5)->withToken($token)->acceptJson()
                ->get(rtrim((string) config('services.auth.url'), '/').'/api/me');
        } catch (\Throwable) {
            abort(503, 'Authentication unavailable');
        }
        if ($response->status() === 401) {
            abort(401);
        }
        if (! $response->successful()) {
            abort(503, 'Authentication unavailable');
        }
        $identity = $response->json('user');
        if (! is_array($identity) || ($identity['role'] ?? '') !== 'admin') {
            abort(403);
        }
        $user = User::firstOrCreate(['email' => $identity['email']], [
            'name' => $identity['name'], 'password' => Str::random(64),
        ]);
        $request->attributes->set('catalog_admin_verified', true);
        Auth::guard('web')->setUser($user);

        return $next($request);
    }
}
