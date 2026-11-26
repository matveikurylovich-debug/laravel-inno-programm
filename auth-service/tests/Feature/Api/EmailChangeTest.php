<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

it('forbids changing email via profile PATCH without confirmation flow', function (): void {
    $user = User::factory()->create(['email' => 'old@example.com']);
    $token = resolve(JwtService::class)->generateTokenPair($user)->accessToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->patchJson('/api/v1/profile', [
            'name' => $user->name,
            'email' => 'new@example.com',
            'phone' => '+375291112233',
        ])->assertStatus(422)
        ->assertJsonValidationErrors('email');

    expect($user->fresh()?->email)->toBe('old@example.com');
});

it('updates email only after visiting the signed confirmation link', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'old@example.com']);
    $token = resolve(JwtService::class)->generateTokenPair($user)->accessToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/profile/email', [
            'email' => 'new@example.com',
        ])->assertStatus(422)
        ->assertJsonValidationErrors('current_password');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/profile/email', [
            'email' => 'new@example.com',
            'current_password' => 'password',
        ])->assertOk();

    expect($user->fresh()?->email)->toBe('old@example.com');

    $confirm = URL::temporarySignedRoute('api.v1.email-change.confirm', now()->addHour(), [
        'user' => $user->id,
        'email' => 'new@example.com',
    ]);
    $this->get($confirm)->assertOk();

    expect($user->fresh()?->email)->toBe('new@example.com');
});
