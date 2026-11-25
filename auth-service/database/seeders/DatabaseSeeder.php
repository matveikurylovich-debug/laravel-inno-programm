<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->isLocal()) {
            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::findOrCreate(RoleName::Admin->value, 'web');
        $customerRole = Role::findOrCreate(RoleName::Customer->value, 'web');
        $analystRole = Role::findOrCreate(RoleName::Analyst->value, 'web');

        $users = [
            ['email' => 'admin@example.com', 'name' => 'Admin User', 'role' => $adminRole],
            ['email' => 'user@example.com', 'name' => 'Regular User', 'role' => $customerRole],
            ['email' => 'analyst@example.com', 'name' => 'Анна Аналитик', 'role' => $analystRole],
            ['email' => 'maria@example.com', 'name' => 'Мария Покупатель', 'role' => $customerRole],
            ['email' => 'ivan@example.com', 'name' => 'Иван Петров', 'role' => $customerRole],
            ['email' => 'olga@example.com', 'name' => 'Ольга Смирнова', 'role' => $customerRole],
        ];

        foreach ($users as $item) {
            $user = User::firstOrCreate(
                ['email' => $item['email']],
                [
                    'name' => $item['name'],
                    'password' => Hash::make('password'),
                ]
            );
            $user->syncRoles($item['role']);
        }
    }
}
