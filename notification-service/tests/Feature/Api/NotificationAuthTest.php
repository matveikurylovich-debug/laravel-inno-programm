<?php

namespace Tests\Feature\Api;

use Ecommerce\PlatformKit\Token\AccessTokenSigner;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;
use Tests\HttpTestCase;

class NotificationAuthTest extends HttpTestCase
{
    private string $secret = 'test-secret-key-for-jwt-testing-12345';

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function signer(): AccessTokenSigner
    {
        $pem = (string) file_get_contents(dirname(__DIR__, 4).'/packages/platform-kit/tests/fixtures/access-private.pem');

        return new AccessTokenSigner($pem, 'test-1', 'http://auth-app', 'ecommerce');
    }

    private function generateToken(array $roles = ['admin'], int $ttl = 900, string $type = 'access', ?string $customSecret = null): string
    {
        $now = time();
        if ($customSecret !== null) {
            return JWT::encode([
                'iss' => 'http://auth-app',
                'aud' => 'ecommerce',
                'sub' => 1,
                'roles' => $roles,
                'type' => $type,
                'jti' => 'wrong-key',
                'iat' => $now,
                'nbf' => $now,
                'exp' => $now + $ttl,
            ], $customSecret, 'HS256');
        }

        return $this->signer()->issue(1, $roles, null, [
            'type' => $type,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
        ]);
    }

    public function test_access_denied_without_authorization_header(): void
    {
        $response = $this->getJson('/api/notifications');

        $response->assertUnauthorized();
    }

    public function test_access_denied_with_invalid_signature(): void
    {
        $token = $this->generateToken(roles: ['admin'], customSecret: 'wrong-secret-key-that-is-at-least-32-bytes-long');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/notifications');

        $response->assertUnauthorized();
    }

    public function test_access_denied_with_expired_token(): void
    {
        $token = $this->generateToken(roles: ['admin'], ttl: -60);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/notifications');

        $response->assertUnauthorized();
    }

    public function test_access_denied_with_refresh_token_type(): void
    {
        $token = $this->generateToken(roles: ['admin'], type: 'refresh');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/notifications');

        $response->assertUnauthorized();
    }

    public function test_notification_routes_require_token_without_role(): void
    {
        $routes = collect(Route::getRoutes())->filter(
            fn ($route) => str_starts_with($route->uri(), 'api/notifications'),
        );

        $this->assertNotEmpty($routes);

        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();

            $this->assertContains('auth:api', $middleware);
            $this->assertFalse(
                collect($middleware)->contains(fn (string $item) => str_starts_with($item, 'role:')),
            );
        }
    }

    public function test_replay_requires_authorization_header(): void
    {
        $this->postJson('/api/notifications/1/replay')->assertUnauthorized();
    }

    public function test_admin_can_pass_middleware(): void
    {
        Route::get('/api/test-protected', function () {
            return response()->json(['status' => 'ok']);
        })->middleware(['auth:api', 'role:admin,analyst']);

        $token = $this->generateToken(roles: ['admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/test-protected');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_analyst_can_pass_middleware(): void
    {
        Route::get('/api/test-protected-analyst', function () {
            return response()->json(['status' => 'ok']);
        })->middleware(['auth:api', 'role:admin,analyst']);

        $token = $this->generateToken(roles: ['analyst']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/test-protected-analyst');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_case_insensitive_roles_accepted(): void
    {
        Route::get('/api/test-protected-case', function () {
            return response()->json(['status' => 'ok']);
        })->middleware(['auth:api', 'role:admin,analyst']);

        $token = $this->generateToken(roles: ['Admin']);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/test-protected-case');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }

    public function test_single_role_field_accepted(): void
    {
        Route::get('/api/test-protected-single-role', function () {
            return response()->json(['status' => 'ok']);
        })->middleware(['auth:api', 'role:admin,analyst']);

        $now = time();
        $token = $this->signer()->issue(1, [], null, [
            'role' => 'Analyst',
            'roles' => null,
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 900,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/test-protected-single-role')
            ->assertForbidden();
    }
}
