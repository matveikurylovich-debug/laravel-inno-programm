<?php

namespace Tests\Feature\Api;

use Ecommerce\PlatformKit\Token\AccessTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Route;
use Tests\HttpTestCase;

class NotificationAuthTest extends HttpTestCase
{
    private string $secret = 'test-secret-key-for-jwt-testing-12345';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'jwt.keys.access' => $this->secret,
            'jwt.algo' => 'HS256',
            'platform-kit.access_secret' => $this->secret,
            'platform-kit.algorithm' => 'HS256',
        ]);
        $this->app->forgetInstance(AccessTokenVerifier::class);
    }

    private function generateToken(array $roles = ['admin'], int $ttl = 900, string $type = 'access', ?string $customSecret = null): string
    {
        $payload = [
            'iss' => 'http://localhost:8000',
            'sub' => 1,
            'roles' => $roles,
            'type' => $type,
            'iat' => time(),
            'exp' => time() + $ttl,
        ];

        return JWT::encode($payload, $customSecret ?? $this->secret, 'HS256');
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

        $payload = [
            'iss' => 'http://localhost:8000',
            'sub' => 1,
            'role' => 'Analyst',
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + 900,
        ];
        $token = JWT::encode($payload, $this->secret, 'HS256');

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/test-protected-single-role');

        $response->assertStatus(200)
            ->assertJson(['status' => 'ok']);
    }
}
