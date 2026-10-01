<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProfileApiTest extends TestCase
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

    public function test_register_rejects_missing_phone(): void
    {
        $this->postJson('/api/register', [
            'name' => 'New User',
            'email' => 'new@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('phone');
    }

    public function test_user_can_update_profile_information(): void
    {
        $user = User::factory()->create([
            'phone' => '+375 (29) 111-11-11',
        ]);
        $user->assignRole('customer');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/profile', [
                'name' => 'Updated User',
                'email' => 'updated@example.com',
                'phone' => '+375 (29) 222-22-22',
            ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Updated User')
            ->assertJsonPath('user.phone', '+375 (29) 222-22-22');

        $user->refresh();
        $this->assertSame('Updated User', $user->name);
        $this->assertSame('updated@example.com', $user->email);
        $this->assertSame('+375 (29) 222-22-22', $user->phone);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_stays_when_email_is_unchanged(): void
    {
        $user = User::factory()->create([
            'phone' => '+375 (29) 111-11-11',
        ]);
        $user->assignRole('customer');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->patchJson('/api/profile', [
                'name' => 'Same Email',
                'email' => $user->email,
                'phone' => $user->phone,
            ])
            ->assertOk();

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_change_password(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson('/api/profile/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertOk();

        $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'new-password',
        ])->assertOk()
            ->assertJsonPath('two_factor', true);
    }

    public function test_password_change_rejects_wrong_current_password(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($user))
            ->putJson('/api/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('current_password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_user_can_delete_account(): void
    {
        $user = User::factory()->create();
        $user->assignRole('customer');
        $token = $this->tokenFor($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->deleteJson('/api/profile', ['password' => 'password'])
            ->assertOk();

        $this->assertSoftDeleted($user);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/me')
            ->assertUnauthorized();
    }
}
