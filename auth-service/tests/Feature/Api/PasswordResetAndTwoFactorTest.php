<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Events\TwoFactorCodeRequested;
use App\Messaging\Producers\AuthEventsProducer;
use App\Models\User;
use Ecommerce\PlatformKit\Messaging\EventEnvelope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Junges\Kafka\Facades\Kafka;
use Mockery\MockInterface;

it('sends a two-factor challenge instead of issuing tokens directly on login', function (): void {
    Event::fake([TwoFactorCodeRequested::class]);

    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $response = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('two_factor', true)
        ->assertJsonMissingPath('user_id')
        ->assertJsonMissingPath('access_token');

    $challengeId = $response->json('challenge_id');
    expect($challengeId)->toBeString()
        ->and(strlen((string) $challengeId))->toBe(40);

    $cached = Cache::get('2fa_challenge_'.$challengeId);
    expect($cached)->toBeArray()
        ->and($cached)->not->toHaveKey('code');

    Event::assertDispatched(TwoFactorCodeRequested::class, fn (TwoFactorCodeRequested $event): bool => is_array($cached)
        && isset($cached['code_hash'])
        && is_string($cached['code_hash'])
        && preg_match('/^\d{6}$/', $event->code) === 1
        && hash_equals($cached['code_hash'], hash('sha256', $event->code)));
});

it('issues JWT on valid two-factor code and rejects wrong code', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $challenge = $this->twoFactorChallenge($user);

    $this->postJson('/api/v1/two-factor/verify', [
        'user_id' => $user->id,
        'code' => $challenge['code'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_id');

    $wrong = $challenge['code'] === '000000' ? '111111' : '000000';
    $this->postJson('/api/v1/two-factor/verify', [
        'challenge_id' => $challenge['challenge_id'],
        'code' => $wrong,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->postJson('/api/v1/two-factor/verify', [
        'challenge_id' => $challenge['challenge_id'],
        'code' => $challenge['code'],
    ])->assertOk()
        ->assertJsonStructure(['access_token', 'refresh_token', 'user']);

    expect(Cache::get('2fa_challenge_'.$challenge['challenge_id']))->toBeNull();
});

it('locks two-factor challenge after five failed attempts and requires active challenge for resend', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $this->postJson('/api/v1/two-factor/resend', [
        'user_id' => $user->id,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_id');

    $challenge = $this->twoFactorChallenge($user);
    $wrong = $challenge['code'] === '000000' ? '111111' : '000000';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/two-factor/verify', [
            'challenge_id' => $challenge['challenge_id'],
            'code' => $wrong,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    $this->postJson('/api/v1/two-factor/verify', [
        'challenge_id' => $challenge['challenge_id'],
        'code' => $wrong,
    ])->assertStatus(429);

    $this->postJson('/api/v1/two-factor/verify', [
        'challenge_id' => $challenge['challenge_id'],
        'code' => $challenge['code'],
    ])->assertStatus(429);

    expect(Cache::get('2fa_challenge_'.$challenge['challenge_id']))->toBeNull();

    $replacement = $this->twoFactorChallenge($user);
    $this->postJson('/api/v1/two-factor/resend', [
        'challenge_id' => $replacement['challenge_id'],
    ])->assertOk()
        ->assertJsonPath('challenge_id', $replacement['challenge_id'])
        ->assertJsonMissingPath('user_id');
});

it('clears outstanding password reset tokens via artisan command', function (): void {
    DB::table('password_reset_tokens')->insert([
        'email' => 'admin@example.com',
        'token' => hash('sha256', 'stale-link'),
        'created_at' => now(),
    ]);

    $this->artisan('auth:invalidate-password-resets')->assertSuccessful();

    $this->assertDatabaseCount('password_reset_tokens', 0);
});

it('publishes two-factor code event to Kafka with required fields', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);
    $challenge = $this->twoFactorChallenge($user);

    Kafka::assertPublishedOn('auth.events', null, function (mixed $message) use ($user, $challenge): bool {
        if (! is_object($message) || ! method_exists($message, 'getBody')) {
            return false;
        }
        $rawBody = $message->getBody();
        $body = EventEnvelope::flatten(is_array($rawBody) ? $rawBody : []);
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];

        return ($body['event'] ?? null) === 'auth.two_factor_code'
            && ($body['email'] ?? null) === $user->email
            && (string) ($body['user_id'] ?? '') === (string) $user->id
            && ($data['code'] ?? null) === $challenge['code']
            && is_int($data['ttl_minutes'] ?? null);
    });
});

it('publishes password reset link with frontend URL on forgot-password', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $this->postJson('/api/v1/forgot-password', [
        'email' => $user->email,
    ])->assertOk();

    Kafka::assertPublishedOn('auth.events', null, function (mixed $message) use ($user): bool {
        if (! is_object($message) || ! method_exists($message, 'getBody')) {
            return false;
        }
        $rawBody = $message->getBody();
        $body = EventEnvelope::flatten(is_array($rawBody) ? $rawBody : []);
        $data = is_array($body['data'] ?? null) ? $body['data'] : [];
        $resetUrl = $data['reset_url'] ?? null;

        return ($body['event'] ?? null) === 'auth.password_reset'
            && ($body['email'] ?? null) === $user->email
            && (string) ($body['user_id'] ?? '') === (string) $user->id
            && is_string($resetUrl)
            && str_contains($resetUrl, '/reset-password/')
            && is_int($data['expires_in'] ?? null);
    });
});

it('builds the password reset link from the browser origin', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $captured = null;
    $this->mock(AuthEventsProducer::class, function (MockInterface $mock) use (&$captured): void {
        $mock->shouldReceive('sendPasswordReset')
            ->once()
            ->andReturnUsing(function (mixed ...$args) use (&$captured): void {
                $captured = $args[3] ?? null;
            });
    });

    $this->withHeader('Origin', 'https://shop.example')
        ->postJson('http://shop.example/api/v1/forgot-password', [
            'email' => $user->email,
        ])->assertOk();

    expect((string) $captured)->toStartWith('https://shop.example/reset-password/');
});

it('resets user password with a valid reset token', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $captured = null;
    $this->mock(AuthEventsProducer::class, function (MockInterface $mock) use (&$captured): void {
        $mock->shouldReceive('sendPasswordReset')
            ->once()
            ->andReturnUsing(function (mixed ...$args) use (&$captured): void {
                $captured = $args[3] ?? null;
            });
    });

    $this->postJson('/api/v1/forgot-password', [
        'email' => $user->email,
    ])->assertOk();

    expect($captured)->toBeString()
        ->and((string) $captured)->toContain('http://localhost:3000/reset-password/');

    $parsedPath = parse_url((string) $captured, PHP_URL_PATH);
    $token = is_string($parsedPath) ? basename($parsedPath) : '';
    expect($token)->not->toBe('');

    $this->postJson('/api/v1/reset-password', [
        'token' => $token,
        'email' => $user->email,
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertOk();

    expect(Hash::check('new-password', (string) $user->refresh()->password))->toBeTrue();
});
