<?php

namespace Tests\Feature\Api;

use App\Models\User;
use App\Services\JwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RefreshLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_reusing_a_rotated_refresh_revokes_every_session(): void
    {
        $user = User::factory()->create();
        $issued = app(JwtService::class)->generateTokenPair($user);

        $rotated = $this->postJson('/api/refresh', [
            'refresh_token' => $issued['refresh_token'],
        ])->assertOk();

        $replayed = $this->postJson('/api/refresh', [
            'refresh_token' => $issued['refresh_token'],
        ])->assertOk();
        $this->assertSame($rotated->json('refresh_token'), $replayed->json('refresh_token'));

        $payload = app(JwtService::class)->readRefreshToken($issued['refresh_token']);
        Redis::del('jwt:refresh:grace:'.$user->id.':'.$payload->jti);

        $this->postJson('/api/refresh', [
            'refresh_token' => $issued['refresh_token'],
        ])->assertUnauthorized();

        $this->postJson('/api/refresh', [
            'refresh_token' => $rotated->json('refresh_token'),
        ])->assertUnauthorized();

        $this->withHeader('Authorization', 'Bearer '.$issued['access_token'])
            ->getJson('/api/me')
            ->assertUnauthorized();
    }

    public function test_password_change_revokes_access_and_refresh(): void
    {
        $user = User::factory()->create();
        $issued = app(JwtService::class)->generateTokenPair($user);

        $this->withHeader('Authorization', 'Bearer '.$issued['access_token'])
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'new-password-1',
                'password_confirmation' => 'new-password-1',
            ])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', 'Bearer '.$issued['access_token'])
            ->getJson('/api/me')
            ->assertUnauthorized();

        $this->postJson('/api/refresh', [
            'refresh_token' => $issued['refresh_token'],
        ])->assertUnauthorized();
    }

    public function test_logout_rejects_a_bad_refresh_and_consumed_refresh_stops_working(): void
    {
        $user = User::factory()->create();
        $issued = app(JwtService::class)->generateTokenPair($user);

        $this->withHeader('Authorization', 'Bearer '.$issued['access_token'])
            ->postJson('/api/logout', ['refresh_token' => 'not-a-token'])
            ->assertStatus(422);

        $this->withHeader('Authorization', 'Bearer '.$issued['access_token'])
            ->postJson('/api/logout', ['refresh_token' => $issued['refresh_token']])
            ->assertOk();

        $this->postJson('/api/refresh', [
            'refresh_token' => $issued['refresh_token'],
        ])->assertUnauthorized();
    }
}
