<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('rejects registration when phone is missing', function (): void {
    $this->postJson('/api/v1/register', [
        'name' => 'New User',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('phone');
});

it('updates profile information and normalizes phone', function (): void {
    $user = User::factory()->create([
        'phone' => '+375 (29) 111-11-11',
    ]);
    $user->assignRole(RoleName::Customer);

    $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
        ->patchJson('/api/v1/profile', [
            'name' => 'Updated User',
            'email' => $user->email,
            'phone' => '+375 29 222 22 22',
        ])
        ->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'phone', 'roles', 'role', 'registered_at'],
        ])
        ->assertJsonPath('user.name', 'Updated User')
        ->assertJsonPath('user.email', $user->email)
        ->assertJsonPath('user.phone', '+375292222222')
        ->assertJsonPath('user.roles', ['customer']);

    $user->refresh();
    expect($user->name)->toBe('Updated User')
        ->and($user->phone)->toBe('+375292222222')
        ->and($user->email_verified_at)->not->toBeNull();
});

it('preserves saved E.164 phone numbers without corruption on profile update', function (): void {
    foreach (['+375291112233', '+48123456789'] as $index => $savedPhone) {
        $user = User::factory()->create([
            'email' => "saved-phone-{$index}@example.com",
            'phone' => $savedPhone,
        ]);
        $user->assignRole(RoleName::Customer);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/v1/profile', [
                'name' => 'Renamed User',
                'email' => $user->email,
                'phone' => $user->phone,
            ])
            ->assertOk()
            ->assertJsonPath('user.phone', $savedPhone);

        expect($user->refresh()->phone)->toBe($savedPhone);
    }
});

it('keeps email verification timestamp when email is unchanged', function (): void {
    $user = User::factory()->create([
        'phone' => '+375 (29) 111-11-11',
    ]);
    $user->assignRole(RoleName::Customer);

    $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
        ->patchJson('/api/v1/profile', [
            'name' => 'Same Email',
            'email' => $user->email,
            'phone' => $user->phone,
        ])
        ->assertOk();

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

it('changes user password and allows login with the new password', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertOk();

    $this->postJson('/api/v1/login', [
        'email' => $user->email,
        'password' => 'new-password',
    ])->assertOk()
        ->assertJsonPath('two_factor', true);
});

it('rejects password change with wrong current password', function (): void {
    $user = User::factory()->create();
    $user->assignRole(RoleName::Customer);

    $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
        ->putJson('/api/v1/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');

    expect(Hash::check('password', (string) $user->refresh()->password))->toBeTrue();
});

it('soft-deletes account, revokes all sessions, and allows re-registering with the exact same email', function (): void {
    $user = User::factory()->create([
        'email' => 'reusable@example.com',
    ]);
    $user->assignRole(RoleName::Customer);
    $tokens = $this->tokensFor($user);

    $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
        ->deleteJson('/api/v1/profile', ['password' => 'password'])
        ->assertOk();

    $this->assertSoftDeleted($user);
    $this->app['auth']->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$tokens['access_token'])
        ->getJson('/api/v1/me')
        ->assertUnauthorized();

    $this->postJson('/api/v1/refresh', [
        'refresh_token' => $tokens['refresh_token'],
    ])->assertUnauthorized();

    $reregistered = $this->postJson('/api/v1/register', [
        'name' => 'Returning User',
        'email' => 'reusable@example.com',
        'phone' => '+375291234567',
        'password' => 'new-password-123',
        'password_confirmation' => 'new-password-123',
    ]);

    $reregistered->assertCreated()
        ->assertJsonPath('two_factor', true)
        ->assertJsonMissingPath('access_token');

    expect(User::withTrashed()->where('email', 'reusable@example.com')->count())->toBe(2)
        ->and(User::query()->where('email', 'reusable@example.com')->count())->toBe(1);
});

it('prunes soft-deleted users older than 30 days', function (): void {
    $expired = User::factory()->create(['email' => 'expired@example.com']);
    $expired->forceFill(['deleted_at' => now()->subDays(31)])->save();

    $recent = User::factory()->create(['email' => 'recent@example.com']);
    $recent->forceFill(['deleted_at' => now()->subDays(5)])->save();

    $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

    $this->assertDatabaseMissing('users', ['id' => $expired->id]);
    $this->assertDatabaseHas('users', ['id' => $recent->id]);
});
