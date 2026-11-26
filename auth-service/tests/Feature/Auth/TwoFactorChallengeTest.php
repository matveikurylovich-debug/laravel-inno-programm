<?php

declare(strict_types=1);

use App\Events\TwoFactorCodeRequested;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Cache::flush();
});

test('resend without a live challenge is rejected', function (): void {
    $this->postJson('/api/v1/two-factor/resend', [
        'user_id' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_id');

    $this->postJson('/api/v1/two-factor/resend', [
        'challenge_id' => str_repeat('a', 40),
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('challenge_id');
});

test('the sixth wrong code burns the challenge', function (): void {
    $user = User::factory()->create();
    $code = null;
    Event::listen(TwoFactorCodeRequested::class, function (TwoFactorCodeRequested $event) use (&$code): void {
        $code = $event->code;
    });

    $challengeId = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->json('challenge_id');

    $wrong = $code === '111111' ? '222222' : '111111';

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/two-factor/verify', [
            'challenge_id' => $challengeId,
            'code' => $wrong,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('code');
    }

    $this->postJson('/api/v1/two-factor/verify', [
        'challenge_id' => $challengeId,
        'code' => $wrong,
    ])->assertStatus(429);

    $this->postJson('/api/v1/two-factor/verify', [
        'challenge_id' => $challengeId,
        'code' => $code,
    ])->assertStatus(429);
});

test('the fourth resend is rejected', function (): void {
    $user = User::factory()->create();

    $challengeId = $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertOk()->json('challenge_id');

    for ($attempt = 0; $attempt < 3; $attempt++) {
        $this->postJson('/api/v1/two-factor/resend', [
            'challenge_id' => $challengeId,
        ])->assertOk()
            ->assertJsonPath('challenge_id', $challengeId);
    }

    $this->postJson('/api/v1/two-factor/resend', [
        'challenge_id' => $challengeId,
    ])->assertStatus(429);
});
