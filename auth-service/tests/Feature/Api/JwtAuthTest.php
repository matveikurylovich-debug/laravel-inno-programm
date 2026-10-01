<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JwtAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Очищаем тестовый Redis перед каждым тестом
        Redis::flushall();

        // Создаем роль Spatie для тестов
        Role::create(['name' => 'customer']);
        Role::create(['name' => 'admin']);
    }

    protected function tearDown(): void
    {
        // Очищаем Redis после завершения теста
        Redis::flushall();
        parent::tearDown();
    }

    /**
     * Тест 1: Успешный вход требует 2FA, затем выдаёт JWT с ролями Spatie
     */
    public function test_user_can_login_and_receive_jwt_tokens_with_roles(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $user->assignRole('customer');

        $challenge = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $challenge->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonMissingPath('access_token');

        $response = $this->postJson('/api/two-factor/verify', [
            'user_id' => $user->id,
            'code' => Cache::get("2fa_code_{$user->id}"),
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'access_token',
                'refresh_token',
                'token_type',
                'expires_in',
                'user' => ['id', 'email', 'role'],
            ])
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('expires_in', config('jwt.ttl.access'));
    }

    /**
     * Тест 2: Доступ к закрытому эндпоинту с валидным токеном
     */
    public function test_user_can_access_protected_route_with_valid_access_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $accessToken = $this->tokenFor($user);

        // Делаем запрос к /me с заголовком Authorization: Bearer
        $response = $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);
    }

    /**
     * Тест 3: Защита роута от запросов без токена или с невалидной подписью
     */
    public function test_protected_route_rejects_missing_or_invalid_tokens(): void
    {
        // Запрос без токена
        $this->getJson('/api/me')
            ->assertStatus(401)
            ->assertJson(['error' => 'Заголовок Authorization: Bearer отсутствует']);

        // Запрос с фальшивым/битым токеном
        $this->withHeader('Authorization', 'Bearer invalid.token.payload')
            ->getJson('/api/me')
            ->assertStatus(401);
    }

    /**
     * Тест 4: Ротация Refresh-токена и удаление использованного из Redis
     */
    public function test_refresh_token_rotation_and_redis_invalidation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $oldRefreshToken = $this->tokensFor($user)['refresh_token'];

        // 1. Первый refresh — должен пройти успешно и вернуть новую пару
        $refreshResponse = $this->postJson('/api/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);

        $refreshResponse->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token']);

        $newRefreshToken = $refreshResponse->json('refresh_token');
        $this->assertNotEquals($oldRefreshToken, $newRefreshToken);

        // 2. Повторная попытка использовать старый токен (он должен быть удалён из Redis)
        $replayAttackResponse = $this->postJson('/api/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);

        $replayAttackResponse->assertStatus(401)
            ->assertJson(['error' => 'Недействительный или отозванный refresh-токен']);
    }

    /**
     * Тест 5: Logout заносит Access-токен в Redis Blacklist
     */
    public function test_logout_blacklists_access_token_in_redis(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $tokens = $this->tokensFor($user);
        $accessToken = $tokens['access_token'];
        $refreshToken = $tokens['refresh_token'];

        // Выполняем logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->postJson('/api/logout', [
                'refresh_token' => $refreshToken,
            ]);

        $logoutResponse->assertStatus(200);

        // Попытка снова сделать запрос с тем же access-токеном
        $retryResponse = $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->getJson('/api/me');

        // Middleware должен заблокировать по Redis Blacklist
        $retryResponse->assertStatus(401)
            ->assertJson(['error' => 'Токен отозван (находится в блэклисте).']);
    }

    public function test_register_assigns_customer_role_and_dispatches_event(): void
    {
        Event::fake([Registered::class]);

        $response = $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'phone' => '+375 (29) 123-45-67',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertCreated()
            ->assertJsonPath('user.name', 'New User')
            ->assertJsonPath('user.email', 'new@example.com')
            ->assertJsonPath('user.phone', '+375 (29) 123-45-67')
            ->assertJsonPath('user.role', 'customer')
            ->assertJsonPath('token_type', 'Bearer');

        $this->assertNotEmpty($response->json('access_token'));
        $this->assertDatabaseHas('users', [
            'email' => 'new@example.com',
            'phone' => '+375 (29) 123-45-67',
        ]);
        Event::assertDispatched(Registered::class);
    }
}
