<?php

namespace Tests\Feature\Api;

use App\Events\PasswordResetLinkRequested;
use App\Events\TwoFactorCodeRequested;
use App\Models\User;
use App\Services\Kafka\AuthNotificationProducer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk()
            ->assertJsonPath('two_factor', true)
            ->assertJsonMissingPath('user_id')
            ->assertJsonMissingPath('access_token');

        $challengeId = $response->json('challenge_id');
        $this->assertIsString($challengeId);
        $cached = Cache::get('2fa_challenge_'.$challengeId);
        $this->assertIsArray($cached);
        $this->assertArrayNotHasKey('code', $cached);

        $this->assertSame(40, strlen((string) $challengeId));

        Event::assertDispatched(TwoFactorCodeRequested::class, function (TwoFactorCodeRequested $event) use ($cached) {
            return preg_match('/^\d{6}$/', $event->code) === 1
                && hash_equals($cached['code_hash'], hash('sha256', $event->code));
        });
    }

    public function test_two_factor_verify_issues_jwt_and_rejects_wrong_code(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $challenge = $this->twoFactorChallenge($user);

        $this->postJson('/api/two-factor/verify', [
            'user_id' => $user->id,
            'code' => $challenge['code'],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');

        $wrong = $challenge['code'] === '000000' ? '111111' : '000000';
        $this->postJson('/api/two-factor/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $wrong,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');

        $this->postJson('/api/two-factor/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['code'],
        ])->assertOk()
            ->assertJsonStructure(['access_token', 'refresh_token', 'user']);

        $this->assertNull(Cache::get('2fa_challenge_'.$challenge['challenge_id']));
    }

    public function test_two_factor_locks_after_five_attempts_and_resend_requires_challenge(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->postJson('/api/two-factor/resend', [
            'user_id' => $user->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('challenge_id');

        $challenge = $this->twoFactorChallenge($user);
        $wrong = $challenge['code'] === '000000' ? '111111' : '000000';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson('/api/two-factor/verify', [
                'challenge_id' => $challenge['challenge_id'],
                'code' => $wrong,
            ])->assertUnprocessable()
                ->assertJsonValidationErrors('code');
        }

        $this->postJson('/api/two-factor/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $wrong,
        ])->assertStatus(429);

        $this->postJson('/api/two-factor/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $challenge['code'],
        ])->assertStatus(429);

        $this->assertNull(Cache::get('2fa_challenge_'.$challenge['challenge_id']));

        $replacement = $this->twoFactorChallenge($user);
        $this->postJson('/api/two-factor/resend', [
            'challenge_id' => $replacement['challenge_id'],
        ])->assertOk()
            ->assertJsonPath('challenge_id', $replacement['challenge_id'])
            ->assertJsonMissingPath('user_id');
    }

    public function test_invalidate_password_resets_clears_outstanding_links(): void
    {
        DB::table('password_reset_tokens')->insert([
            'email' => 'admin@example.com',
            'token' => hash('sha256', 'stale-link'),
            'created_at' => now(),
        ]);

        $this->artisan('auth:invalidate-password-resets')->assertSuccessful();

        $this->assertDatabaseCount('password_reset_tokens', 0);
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
