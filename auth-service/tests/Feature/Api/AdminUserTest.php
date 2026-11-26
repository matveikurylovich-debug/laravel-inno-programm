<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\RoleAuditLog;
use App\Models\User;

it('returns paginated users, roles catalog, and writes an audit log on role change', function (): void {
    $admin = User::factory()->create(['email' => 'admin@example.com']);
    $admin->assignRole(RoleName::Admin);

    $customer = User::factory()->create(['email' => 'customer@example.com']);
    $customer->assignRole(RoleName::Customer);

    $token = $this->tokenFor($admin);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/users')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'email', 'phone', 'roles', 'role', 'registered_at'],
            ],
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            'roles',
        ])
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('roles', ['admin', 'analyst', 'customer'])
        ->assertJsonPath('data.1.email', 'customer@example.com')
        ->assertJsonPath('data.1.roles', ['customer'])
        ->assertJsonPath('data.1.role', 'customer');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/roles')
        ->assertOk()
        ->assertExactJson([
            'roles' => ['admin', 'analyst', 'customer'],
        ]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/admin/users/{$customer->id}/role", ['role' => 'unknown-role'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/admin/users/{$customer->id}/role", ['role' => RoleName::Analyst->value])
        ->assertOk()
        ->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'phone', 'roles', 'role', 'registered_at'],
        ])
        ->assertJsonPath('user.roles', ['analyst'])
        ->assertJsonPath('user.role', 'analyst');

    expect($customer->fresh()?->hasRole(RoleName::Analyst))->toBeTrue()
        ->and($customer->fresh()?->hasRole(RoleName::Customer))->toBeFalse();

    $log = RoleAuditLog::query()->first();
    expect($log)->not->toBeNull()
        ->and($log?->actor_id)->toBe($admin->id)
        ->and($log?->target_user_id)->toBe($customer->id)
        ->and($log?->old_role)->toBe('customer')
        ->and($log?->new_role)->toBe('analyst')
        ->and($log?->created_at)->not->toBeNull();
});

it('prevents demoting the last administrator with 422', function (): void {
    $onlyAdmin = User::factory()->create(['email' => 'only-admin@example.com']);
    $onlyAdmin->assignRole(RoleName::Admin);

    $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($onlyAdmin))
        ->patchJson("/api/v1/admin/users/{$onlyAdmin->id}/role", ['role' => RoleName::Customer->value])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'last_admin_protected')
        ->assertJsonPath('detail', 'Нельзя снять роль с последнего администратора.');

    expect($onlyAdmin->fresh()?->hasRole(RoleName::Admin))->toBeTrue()
        ->and(RoleAuditLog::query()->count())->toBe(0);
});

it('prevents an administrator from changing own role when another administrator exists', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin);

    $secondAdmin = User::factory()->create();
    $secondAdmin->assignRole(RoleName::Admin);

    $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
        ->patchJson("/api/v1/admin/users/{$admin->id}/role", ['role' => RoleName::Customer->value])
        ->assertForbidden()
        ->assertJsonPath('code', 'self_role_modification_forbidden')
        ->assertJsonPath('detail', 'Нельзя изменить роль собственной учетной записи.');

    expect($admin->fresh()?->hasRole(RoleName::Admin))->toBeTrue();
});

it('allows an analyst to read users and roles but forbids changing roles', function (): void {
    $analyst = User::factory()->create();
    $analyst->assignRole(RoleName::Analyst);

    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer);

    $token = $this->tokenFor($analyst);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/users')
        ->assertOk()
        ->assertJsonFragment(['email' => $customer->email]);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/roles')
        ->assertOk();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/admin/users/{$customer->id}/role", ['role' => RoleName::Admin->value])
        ->assertForbidden();
});

it('forbids customers and rejects unauthenticated guests on admin endpoints', function (): void {
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer);

    $other = User::factory()->create();
    $other->assignRole(RoleName::Customer);

    $token = $this->tokenFor($customer);

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/users')
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->getJson('/api/v1/admin/roles')
        ->assertForbidden();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->patchJson("/api/v1/admin/users/{$other->id}/role", ['role' => RoleName::Admin->value])
        ->assertForbidden();

    $this->flushHeaders();
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/admin/users')->assertUnauthorized();
    $this->getJson('/api/v1/admin/roles')->assertUnauthorized();
    $this->patchJson("/api/v1/admin/users/{$other->id}/role", ['role' => RoleName::Admin->value])->assertUnauthorized();
});
