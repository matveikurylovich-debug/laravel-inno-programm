<?php

declare(strict_types=1);

use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        resolve(PermissionRegistrar::class)->forgetCachedPermissions();

        $granted = [
            RoleName::Admin->value => [
                'catalog.write', 'notifications.read', 'notifications.replay',
                'orders.view', 'orders.view-any', 'orders.cancel', 'orders.cancel-any',
            ],
            RoleName::Analyst->value => ['notifications.read', 'orders.view', 'orders.view-any'],
            RoleName::Customer->value => ['orders.view', 'orders.cancel'],
        ];

        foreach ($granted as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');
            foreach ($permissions as $permission) {
                $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
            }
        }
    }

    public function down(): void
    {
        Permission::query()->whereIn('name', [
            'catalog.write', 'notifications.read', 'notifications.replay',
            'orders.view', 'orders.view-any', 'orders.cancel', 'orders.cancel-any',
        ])->delete();
    }
};
