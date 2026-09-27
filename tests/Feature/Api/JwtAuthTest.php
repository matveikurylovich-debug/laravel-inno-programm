<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
     * Тест 1: Успешный вход, выдача токенов и зашитых ролей Spatie
     */
    public function test_user_can_login_and_receive_jwt_tokens_with_roles(): void
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('secret123'),
        ]);
        $user->assignRole('customer');

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'test@example.com',
            'password' => 'secret123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'roles'],
                'tokens' => ['access_token', 'refresh_token', 'token_type', 'expires_in'],
            ])
            ->assertJsonFragment([
                'roles' => ['customer'],
            ]);
    }

    /**
     * Тест 2: Доступ к закрытому эндпоинту с валидным токеном
     */
    public function test_user_can_access_protected_route_with_valid_access_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        // Логинимся и достаем access-токен
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password', // стандартный пароль UserFactory
        ]);

        $accessToken = $loginResponse->json('tokens.access_token');

        // Делаем запрос к /me с заголовком Authorization: Bearer
        $response = $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->getJson('/api/v1/auth/me');

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
        $this->getJson('/api/v1/auth/me')
            ->assertStatus(401)
            ->assertJson(['error' => 'Заголовок Authorization: Bearer отсутствует']);

        // Запрос с фальшивым/битым токеном
        $this->withHeader('Authorization', 'Bearer invalid.token.payload')
            ->getJson('/api/v1/auth/me')
            ->assertStatus(401);
    }

    /**
     * Тест 4: Ротация Refresh-токена и удаление использованного из Redis
     */
    public function test_refresh_token_rotation_and_redis_invalidation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $oldRefreshToken = $loginResponse->json('tokens.refresh_token');

        // 1. Первый refresh — должен пройти успешно и вернуть новую пару
        $refreshResponse = $this->postJson('/api/v1/auth/refresh', [
            'refresh_token' => $oldRefreshToken,
        ]);

        $refreshResponse->assertStatus(200)
            ->assertJsonStructure(['access_token', 'refresh_token']);

        $newRefreshToken = $refreshResponse->json('refresh_token');
        $this->assertNotEquals($oldRefreshToken, $newRefreshToken);

        // 2. Повторная попытка использовать старый токен (он должен быть удалён из Redis)
        $replayAttackResponse = $this->postJson('/api/v1/auth/refresh', [
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

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $accessToken = $loginResponse->json('tokens.access_token');
        $refreshToken = $loginResponse->json('tokens.refresh_token');

        // Выполняем logout
        $logoutResponse = $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->postJson('/api/v1/auth/logout', [
                'refresh_token' => $refreshToken,
            ]);

        $logoutResponse->assertStatus(200);

        // Попытка снова сделать запрос с тем же access-токеном
        $retryResponse = $this->withHeader('Authorization', "Bearer {$accessToken}")
            ->getJson('/api/v1/auth/me');

        // Middleware должен заблокировать по Redis Blacklist
        $retryResponse->assertStatus(401)
            ->assertJson(['error' => 'Токен отозван (находится в блэклисте).']);
    }
}