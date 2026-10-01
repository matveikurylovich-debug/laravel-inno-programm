<?php

use App\Models\User;
use App\Services\Kafka\AuthNotificationProducer;
use Illuminate\Support\Facades\Cache;

use function Pest\Laravel\mock;

test('login stores a two factor code and publishes it', function () {
    $user = User::factory()->create();

    $producer = mock(AuthNotificationProducer::class);
    $producer->shouldReceive('sendTwoFactorCode')
        ->once()
        ->withArgs(function (int|string $userId, string $email, string $code, int $ttlMinutes) use ($user) {
            return (string) $userId === (string) $user->id
                && $email === $user->email
                && preg_match('/^\d{6}$/', $code) === 1
                && $ttlMinutes === 5;
        });

    $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    expect(Cache::get("2fa_code_{$user->id}"))->toMatch('/^\d{6}$/');
});

test('valid two factor code authenticates the user', function () {
    $user = User::factory()->create();
    Cache::put("2fa_code_{$user->id}", '123456', now()->addMinutes(5));

    $this->postJson(route('two-factor.verify'), [
        'user_id' => $user->id,
        'code' => '123456',
    ])->assertOk()
        ->assertJsonPath('user.id', $user->id);

    $this->assertAuthenticatedAs($user);
    expect(Cache::get("2fa_code_{$user->id}"))->toBeNull();
});

test('invalid two factor code is rejected', function () {
    $user = User::factory()->create();
    Cache::put("2fa_code_{$user->id}", '123456', now()->addMinutes(5));

    $this->postJson(route('two-factor.verify'), [
        'user_id' => $user->id,
        'code' => '000000',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->assertGuest();
});
