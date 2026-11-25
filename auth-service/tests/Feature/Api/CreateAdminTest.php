<?php

namespace Tests\Feature\Api;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_migration_creates_exactly_three_roles(): void
    {
        $this->assertSame(
            [RoleName::Admin->value, RoleName::Analyst->value, RoleName::Customer->value],
            Role::query()->orderBy('name')->pluck('name')->all(),
        );
    }

    public function test_create_admin_is_idempotent(): void
    {
        $this->artisan('auth:create-admin', [
            'email' => 'admin@example.test',
            '--password' => 'secret-password',
        ])->assertSuccessful();

        $this->artisan('auth:create-admin', [
            'email' => 'admin@example.test',
            '--password' => 'secret-password',
        ])->assertSuccessful();

        $admins = User::query()->where('email', 'admin@example.test')->get();
        $this->assertCount(1, $admins);
        $this->assertTrue($admins->first()->hasRole(RoleName::Admin));
    }

    public function test_unknown_role_is_rejected(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(RoleName::Admin);
        $customer = User::factory()->create();
        $customer->assignRole(RoleName::Customer);

        $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
            ->patchJson('/api/admin/users/'.$customer->id.'/role', ['role' => 'superuser'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('role');
    }
}
