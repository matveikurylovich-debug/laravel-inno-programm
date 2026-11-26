<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Models\AdminUser;
use Closure;
use Ecommerce\PlatformKit\Laravel\RevocationInspector;
use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\InvalidTokenException;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateCatalogAdmin
{
    /**
     * @param  AccessTokenVerifier|Closure(): AccessTokenVerifier  $verifier
     */
    public function __construct(
        private readonly AccessTokenVerifier|Closure $verifier,
        private readonly ?RevocationInspector $revocation = null,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('catalog-admin', 'catalog-admin/*', 'livewire/*')) {
            return $next($request);
        }

        $cookieToken = $request->cookie('__Host-access') ?: $request->cookie('access_token');
        $token = $request->bearerToken() ?: (is_string($cookieToken) ? $cookieToken : '');

        if ($token === '') {
            if (Auth::guard('web')->hasUser() && Auth::guard('web')->user() instanceof AdminUser) {
                return $next($request);
            }

            abort(401);
        }

        try {
            $verifier = $this->verifier instanceof Closure ? ($this->verifier)() : $this->verifier;
            $claims = $verifier->verify($token);
            $this->revocation?->assertActive($claims, $token);
        } catch (InvalidTokenException) {
            abort(401);
        } catch (TokenInfrastructureException) {
            abort(503, 'Authentication unavailable');
        }

        $roles = array_map(strtolower(...), $claims->roles);

        if (! in_array(RoleName::Admin->value, $roles, true)) {
            abort(403);
        }

        $email = "admin-{$claims->userId}@catalog.internal";
        $user = AdminUser::query()->where('email', $email)->first()
            ?? AdminUser::query()->createOrFirst(
                ['email' => $email],
                [
                    'name' => $claims->email ?? "Admin #{$claims->userId}",
                    'password' => Str::random(64),
                ],
            );

        Auth::guard('web')->setUser($user);

        return $next($request);
    }
}
