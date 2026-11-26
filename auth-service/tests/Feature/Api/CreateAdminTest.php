<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Spatie\Permission\Models\Role;

it('seeds the three canonical roles via migration', function (): void {
    expect(Role::query()->orderBy('name')->pluck('name')->all())
        ->toBe([RoleName::Admin->value, RoleName::Analyst->value, RoleName::Customer->value]);
});

it('creates or updates an admin idempotently via auth:create-admin', function (): void {
    $this->artisan('auth:create-admin', [
        'email' => 'admin@example.test',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $this->artisan('auth:create-admin', [
        'email' => 'admin@example.test',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $admins = User::query()->where('email', 'admin@example.test')->get();
    expect($admins)->toHaveCount(1)
        ->and($admins->first()?->hasRole(RoleName::Admin))->toBeTrue();
});

it('rejects assigning an unknown role', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole(RoleName::Admin);
    $customer = User::factory()->create();
    $customer->assignRole(RoleName::Customer);

    $this->withHeader('Authorization', 'Bearer '.$this->tokenFor($admin))
        ->patchJson('/api/v1/admin/users/'.$customer->id.'/role', ['role' => 'superuser'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
});
