<?php

declare(strict_types=1);

use App\Contracts\RefreshTokenStore;
use App\Models\User;
use App\Services\Auth\JwtService;

it('revokes every session when a rotated refresh token is replayed outside the grace window', function (): void {
    $user = User::factory()->create();
    $issued = resolve(JwtService::class)->generateTokenPair($user);

    $rotated = $this->postJson('/api/v1/refresh', [
        'refresh_token' => $issued->refreshToken,
    ])->assertOk();

    $replayed = $this->postJson('/api/v1/refresh', [
        'refresh_token' => $issued->refreshToken,
    ])->assertOk();

    expect($replayed->json('refresh_token'))->toBe($rotated->json('refresh_token'));

    $payload = resolve(JwtService::class)->readRefreshToken($issued->refreshToken);
    resolve(RefreshTokenStore::class)->forgetGrace((int) $user->id, (string) ($payload->jti ?? ''));

    $this->postJson('/api/v1/refresh', [
        'refresh_token' => $issued->refreshToken,
    ])->assertUnauthorized();

    $this->postJson('/api/v1/refresh', [
        'refresh_token' => $rotated->json('refresh_token'),
    ])->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer '.$issued->accessToken)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('revokes access and refresh tokens when password is changed', function (): void {
    $user = User::factory()->create();
    $issued = resolve(JwtService::class)->generateTokenPair($user);

    $this->withHeader('Authorization', 'Bearer '.$issued->accessToken)
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password-1',
            'password_confirmation' => 'new-password-1',
        ])->assertOk();

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$issued->accessToken)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    $this->postJson('/api/v1/refresh', [
        'refresh_token' => $issued->refreshToken,
    ])->assertUnauthorized();
});

it('rejects a malformed refresh token on logout and invalidates a consumed refresh token', function (): void {
    $user = User::factory()->create();
    $issued = resolve(JwtService::class)->generateTokenPair($user);

    $this->withHeader('Authorization', 'Bearer '.$issued->accessToken)
        ->postJson('/api/v1/logout', ['refresh_token' => 'not-a-token'])
        ->assertStatus(422);

    $this->withHeader('Authorization', 'Bearer '.$issued->accessToken)
        ->postJson('/api/v1/logout', ['refresh_token' => $issued->refreshToken])
        ->assertOk();

    $this->postJson('/api/v1/refresh', [
        'refresh_token' => $issued->refreshToken,
    ])->assertUnauthorized();
});
