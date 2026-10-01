<?php

namespace Tests\Feature\Api;

use App\Events\PasswordResetLinkRequested;
use App\Events\TwoFactorCodeRequested;
use App\Models\User;
use App\Services\Kafka\AuthNotificationProducer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PasswordResetAndTwoFactorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushall();
        Role::create(['name' => 'customer']);
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    public function test_login_sends_two_factor_challenge_instead_of_tokens(): void
    {
        Event::fake([TwoFactorCodeRequested::class]);

        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonMissingPath('access_token');

        $this->assertMatchesRegularExpression('/^\d{6}$/', (string) Cache::get("2fa_code_{$user->id}"));
        Event::assertDispatched(TwoFactorCodeRequested::class);
    }

    public function test_two_factor_verify_issues_jwt_and_rejects_wrong_code(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->postJson('/api/two-factor/verify', [
            'user_id' => $user->id,
            'code' => '000000',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/two-factor/verify', [
            'user_id' => $user->id,
            'code' => Cache::get("2fa_code_{$user->id}"),
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'user']);

        $this->assertNull(Cache::get("2fa_code_{$user->id}"));
    }

    public function test_forgot_password_publishes_reset_link_with_frontend_url(): void
    {
        Event::fake([PasswordResetLinkRequested::class]);

        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        Event::assertDispatched(PasswordResetLinkRequested::class, function (PasswordResetLinkRequested $event) use ($user) {
            return $event->user->is($user) && $event->token !== '';
        });
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $captured = null;
        $this->mock(AuthNotificationProducer::class, function ($mock) use (&$captured) {
            $mock->shouldReceive('sendPasswordReset')
                ->once()
                ->andReturnUsing(function (...$args) use (&$captured) {
                    $captured = $args[3] ?? null;
                });
        });

        $this->postJson('/api/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

        $this->assertIsString($captured);
        $this->assertStringContainsString('http://localhost:3000/reset-password/', $captured);

        $token = (string) (parse_url($captured, PHP_URL_PATH) ? basename((string) parse_url($captured, PHP_URL_PATH)) : '');
        $this->assertNotSame('', $token);

        $this->postJson('/api/reset-password', [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertOk();

        $this->assertTrue(Hash::check('new-password', $user->refresh()->password));
    }
}
