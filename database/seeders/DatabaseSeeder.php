<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Сбрасываем кэш ролей Spatie во избежание коллизий
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // 2. Создаем роли (если их еще нет)
        $adminRole = Role::firstOrCreate(['name' => 'Admin']);
        $customerRole  = Role::firstOrCreate(['name' => 'Customer']);
        $analystRole  = Role::firstOrCreate(['name' => 'Analyst']);

        // 3. Создаем администратора
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
            ]
        );
        $admin->syncRoles($adminRole);

        // 4. Создаем обычного пользователя
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
            ]
        );

        $user->syncRoles($customerRole);
    }
}