<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\JwtService;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class JwtServiceTest extends TestCase
{
    use RefreshDatabase;

    protected JwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();

        // Очищаем тестовый Redis
        Redis::flushall();

        // Получаем экземпляр сервиса из контейнера Laravel
        $this->jwtService = app(JwtService::class);

        Role::create(['name' => 'customer']);
        Role::create(['name' => 'admin']);
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    /**
     * Юнит-тест: генерация пары токенов с корректными claims и ролями
     */
    public function test_generates_valid_token_pair_with_roles(): void
    {
        $user = User::factory()->create();
        $user->assignRole(['customer', 'admin']);

        $tokens = $this->jwtService->generateTokenPair($user);

        $this->assertArrayHasKey('access_token', $tokens);
        $this->assertArrayHasKey('refresh_token', $tokens);
        $this->assertEquals('Bearer', $tokens['token_type']);

        // Декодируем access-токен и проверяем claims
        $payload = $this->jwtService->decodeAccessToken($tokens['access_token']);

        $this->assertEquals($user->id, $payload->sub);
        $this->assertEquals($user->email, $payload->email);
        $this->assertEquals('admin', $payload->role);
        $this->assertEquals('access', $payload->type);
        $this->assertEquals(['customer', 'admin'], (array) $payload->roles);
        $this->assertNotEmpty($payload->exp);
    }

    /**
     * Юнит-тест: валидация подписи — отказ при модификации токена
     */
    public function test_throws_exception_when_token_signature_is_invalid(): void
    {
        $user = User::factory()->create();
        $tokens = $this->jwtService->generateTokenPair($user);

        // Имитируем подделку: меняем последний символ токена
        $tamperedToken = substr($tokens['access_token'], 0, -1).'x';

        $this->expectException(SignatureInvalidException::class);
        $this->jwtService->decodeAccessToken($tamperedToken);
    }

    /**
     * Юнит-тест: валидация exp — отказ при истечении времени токена
     */
    public function test_throws_exception_when_token_is_expired(): void
    {
        // Создаем заведомо просроченный токен вручную
        $now = time();
        $expiredPayload = [
            'iss' => config('app.url'),
            'sub' => 1,
            'roles' => ['customer'],
            'type' => 'access',
            'iat' => $now - 3600,
            'exp' => $now - 100, // протух 100 секунд назад
        ];

        $expiredToken = JWT::encode($expiredPayload, config('jwt.keys.access'), config('jwt.algo'));

        $this->expectException(ExpiredException::class);
        $this->jwtService->decodeAccessToken($expiredToken);
    }

    /**
     * Юнит-тест: проверка типа токена (защита от передачи refresh вместо access)
     */
    public function test_rejects_refresh_token_in_access_decoder(): void
    {
        $user = User::factory()->create();
        $tokens = $this->jwtService->generateTokenPair($user);

        // Refresh подписан другим секретом — access decoder отклонит по подписи
        $this->expectException(SignatureInvalidException::class);

        $this->jwtService->decodeAccessToken($tokens['refresh_token']);
    }

    /**
     * Юнит-тест: валидация блэклиста в Redis для access-токена
     */
    public function test_rejects_access_token_if_present_in_redis_blacklist(): void
    {
        $user = User::factory()->create();
        $tokens = $this->jwtService->generateTokenPair($user);
        $accessToken = $tokens['access_token'];

        // Добавляем токен в Redis блэклист
        $this->jwtService->blacklistAccessToken($accessToken);

        // Сервис должен отклонить токен
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Токен отозван (находится в блэклисте).');

        $this->jwtService->decodeAccessToken($accessToken);
    }
}
