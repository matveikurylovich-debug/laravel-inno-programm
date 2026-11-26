<?php

declare(strict_types=1);

use App\Contracts\RefreshTokenStore;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\Auth\JwtService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

it('completes login via 2FA and issues JWT tokens with Spatie roles', function (): void {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $user->assignRole(RoleName::Customer);

    $challenge = $this->postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'secret123',
    ]);

    $challenge->assertOk()
        ->assertJsonPath('two_factor', true)
        ->assertJsonMissingPath('user_id')
        ->assertJsonMissingPath('access_token');

    $issued = $this->twoFactorChallenge($user, 'secret123');

    $response = $this->postJson('/api/v1/two-factor/verify', [
        'challenge_id' => $issued['challenge_id'],
        'code' => $issued['code'],
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'access_token',
            'refresh_token',
            'token_type',
            'expires_in',
            'user' => ['id', 'name', 'email', 'phone', 'roles', 'role', 'registered_at'],
        ])
        ->assertJsonPath('user.roles', ['customer'])
        ->assertJsonPath('user.role', 'customer')
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', config('jwt.ttl.access'));
});

it('allows accessing protected routes with a valid access token', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $accessToken = $this->tokenFor($user);

    $this->withHeader('Authorization', "Bearer {$accessToken}")
        ->getJson('/api/v1/me')
        ->assertStatus(200)
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('user.email', $user->email);
});

it('rejects missing or invalid tokens on protected routes', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();

    $this->withHeader('Authorization', 'Bearer invalid.token.payload')
        ->getJson('/api/v1/me')
        ->assertStatus(401);
});

it('rejects an access token sent to POST /api/v1/refresh with 401', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $accessToken = $this->tokenFor($user);

    $this->postJson('/api/v1/refresh', [
        'refresh_token' => $accessToken,
    ])->assertStatus(401)
        ->assertHeader('content-type', 'application/problem+json')
        ->assertJsonPath('code', 'invalid_refresh_token')
        ->assertJsonPath('detail', 'Недействительный или отозванный refresh-токен');
});

it('rotates refresh tokens and invalidates replayed tokens outside the grace window', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $oldRefreshToken = $this->tokensFor($user)['refresh_token'];

    $refreshResponse = $this->postJson('/api/v1/refresh', [
        'refresh_token' => $oldRefreshToken,
    ]);

    $refreshResponse->assertStatus(200)
        ->assertJsonStructure(['access_token', 'refresh_token']);

    expect($refreshResponse->json('refresh_token'))->not->toBe($oldRefreshToken);

    $payload = resolve(JwtService::class)->readRefreshToken($oldRefreshToken);
    resolve(RefreshTokenStore::class)->forgetGrace((int) $user->id, (string) ($payload->jti ?? ''));

    $this->postJson('/api/v1/refresh', [
        'refresh_token' => $oldRefreshToken,
    ])->assertStatus(401)
        ->assertJsonPath('detail', 'Недействительный или отозванный refresh-токен');
});

it('blacklists access token on logout', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $tokens = $this->tokensFor($user);

    $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
        ->postJson('/api/v1/logout', [
            'refresh_token' => $tokens['refresh_token'],
        ])
        ->assertStatus(200);

    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

it('rejects registration when the email domain has no dot', function (): void {
    $this->postJson('/api/v1/register', [
        'name' => 'Bad Email',
        'email' => 'a@b',
        'phone' => '+375291112233',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->assertDatabaseMissing('users', ['email' => 'a@b']);
});

it('assigns customer role and dispatches Registered event on registration', function (): void {
    Event::fake([Registered::class]);

    $response = $this->postJson('/api/v1/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'phone' => '+375 (29) 123-45-67',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertCreated()
        ->assertJsonPath('two_factor', true)
        ->assertJsonMissingPath('access_token')
        ->assertJsonMissingPath('user');

    expect($response->json('challenge_id'))->not->toBeEmpty();
    $this->assertDatabaseHas('users', [
        'email' => 'new@example.com',
        'phone' => '+375291234567',
    ]);
    Event::assertDispatched(Registered::class);
});

it('normalizes mixed-case email on registration and login', function (): void {
    Event::fake([Registered::class]);

    $this->postJson('/api/v1/register', [
        'name' => 'Case User',
        'email' => 'User@Mail.com',
        'phone' => '+375 29 555 00 11',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertCreated()
        ->assertJsonPath('two_factor', true)
        ->assertJsonMissingPath('access_token');

    $this->assertDatabaseHas('users', [
        'email' => 'user@mail.com',
        'phone' => '+375295550011',
    ]);
    Event::assertDispatched(Registered::class);

    $this->postJson('/api/v1/register', [
        'name' => 'Duplicate Case User',
        'email' => 'USER@MAIL.COM',
        'phone' => '+375 29 555 00 22',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('email');

    $this->postJson('/api/v1/login', [
        'email' => 'user@mail.com',
        'password' => 'password',
    ])->assertOk()
        ->assertJsonPath('two_factor', true)
        ->assertJsonMissingPath('access_token');
});

it('returns identical response shape from refresh and two-factor verify', function (): void {
    $user = User::factory()->create([
        'email' => 'shape@example.com',
        'phone' => '+375291234567',
    ]);
    $user->assignRole(RoleName::Customer);

    $challenge = $this->twoFactorChallenge($user);

    $verified = $this->postJson('/api/v1/two-factor/verify', [
        'challenge_id' => $challenge['challenge_id'],
        'code' => $challenge['code'],
    ])->assertOk();

    $refreshed = $this->postJson('/api/v1/refresh', [
        'refresh_token' => $verified->json('refresh_token'),
    ])->assertOk();

    /** @var array<string, mixed> $verifiedPayload */
    $verifiedPayload = $verified->json();
    /** @var array<string, mixed> $refreshedPayload */
    $refreshedPayload = $refreshed->json();
    /** @var array<string, mixed> $verifiedUser */
    $verifiedUser = $verified->json('user');
    /** @var array<string, mixed> $refreshedUser */
    $refreshedUser = $refreshed->json('user');

    expect(array_keys($verifiedPayload))->toBe(array_keys($refreshedPayload))
        ->and(array_keys($verifiedUser))->toBe(array_keys($refreshedUser))
        ->and($verifiedUser)->toBe($refreshedUser);
});

it('rate limits login after five attempts', function (): void {
    $user = User::factory()->create([
        'email' => 'throttled@example.com',
        'password' => Hash::make('secret123'),
    ]);
    $user->assignRole(RoleName::Customer);

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson('/api/v1/login', [
            'email' => 'throttled@example.com',
            'password' => 'wrong-password',
        ])->assertUnauthorized();
    }

    $this->postJson('/api/v1/login', [
        'email' => 'throttled@example.com',
        'password' => 'secret123',
    ])->assertStatus(429);
});
