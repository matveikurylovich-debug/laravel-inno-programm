<?php

declare(strict_types=1);

use App\Models\AdminUser;
use Ecommerce\PlatformKit\Laravel\RevocationInspector;
use Ecommerce\PlatformKit\Token\AccessClaims;
use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Ecommerce\PlatformKit\Token\TokenInfrastructureException;
use Illuminate\Support\Facades\Http;

it('requires authentication for direct panel access', function (): void {
    Http::preventStrayRequests();

    $this->get('/catalog-admin')->assertUnauthorized();
    $this->get('/admin')->assertNotFound();
});

it('forbids customer token from entering catalog admin panel without HTTP calls', function (): void {
    Http::preventStrayRequests();

    $this->withToken(catalogJwt('customer'))
        ->get('/catalog-admin')
        ->assertForbidden();
});

it('verifies RS256 admin token locally from __Host-access cookie without HTTP calls and reuses deterministic AdminUser', function (): void {
    Http::preventStrayRequests();

    $token = catalogJwt('admin', 42);

    $this->withUnencryptedCookie('__Host-access', $token)
        ->get('/catalog-admin')
        ->assertOk();

    $admin = AdminUser::query()->where('email', 'admin-42@catalog.internal')->first();
    expect($admin)->not->toBeNull();
    $updatedAt = $admin?->updated_at?->toISOString();

    $this->travel(5)->minutes();

    $this->withUnencryptedCookie('__Host-access', $token)
        ->get('/catalog-admin')
        ->assertOk();

    expect(AdminUser::query()->count())->toBe(1)
        ->and(AdminUser::query()->first()?->updated_at?->toISOString())->toBe($updatedAt);
});

it('rejects malformed token with 401 and returns 503 on token infrastructure failure', function (): void {
    Http::preventStrayRequests();

    $this->withUnencryptedCookie('__Host-access', 'not-a-valid-jwt')
        ->get('/catalog-admin')
        ->assertUnauthorized();

    $this->app->bind(RevocationInspector::class, static fn (): RevocationInspector => new class implements RevocationInspector
    {
        public function assertActive(AccessClaims $claims, string $token): void
        {
            throw new TokenInfrastructureException('Revocation store unavailable');
        }
    });

    $this->withUnencryptedCookie('__Host-access', catalogJwt('admin'))
        ->get('/catalog-admin')
        ->assertStatus(503);

    $this->app->bind(AccessTokenVerifier::class, static function (): AccessTokenVerifier {
        throw new TokenInfrastructureException('JWKS unavailable');
    });

    $this->withUnencryptedCookie('__Host-access', catalogJwt('admin'))
        ->get('/catalog-admin')
        ->assertStatus(503);
});
