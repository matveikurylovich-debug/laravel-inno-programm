<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Redis::flushall();

        Role::create(['name' => 'customer']);
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'analyst']);
    }

    protected function tearDown(): void
    {
        Redis::flushall();
        parent::tearDown();
    }

    public function test_admin_can_list_users_and_change_role(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $admin->assignRole('admin');

        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $customer->assignRole('customer');

        $token = $this->tokenFor($admin);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment([
                'email' => 'customer@example.com',
                'role' => 'customer',
            ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/users/{$customer->id}/role", ['role' => 'analyst'])
            ->assertOk()
            ->assertJsonPath('user.role', 'analyst');

        $this->assertTrue($customer->fresh()->hasRole('analyst'));
        $this->assertFalse($customer->fresh()->hasRole('customer'));
    }

    public function test_admin_cannot_change_own_role(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->patchJson("/api/admin/users/{$admin->id}/role", ['role' => 'customer'])
            ->assertForbidden()
            ->assertJson([
                'error' => 'Нельзя изменить роль собственной учетной записи.',
            ]);

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_customer_cannot_list_or_change_roles(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $other = User::factory()->create();
        $other->assignRole('customer');

        $token = $this->tokenFor($customer);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/users')
            ->assertForbidden();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/users/{$other->id}/role", ['role' => 'admin'])
            ->assertForbidden();
    }

    public function test_analyst_can_read_users_but_cannot_change_roles(): void
    {
        $analyst = User::factory()->create();
        $analyst->assignRole('analyst');

        $customer = User::factory()->create();
        $customer->assignRole('customer');

        $token = $this->tokenFor($analyst);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonFragment(['email' => $customer->email]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/admin/users/{$customer->id}/role", ['role' => 'admin'])
            ->assertForbidden();
    }

    public function test_guest_cannot_list_users(): void
    {
        $this->getJson('/api/admin/users')->assertUnauthorized();
    }
}
