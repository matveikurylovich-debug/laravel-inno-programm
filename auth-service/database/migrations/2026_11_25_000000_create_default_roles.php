<?php

declare(strict_types=1);

use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }
    }

    public function down(): void
    {
        Role::query()->whereIn('name', array_map(
            fn (RoleName $role) => $role->value,
            RoleName::cases(),
        ))->delete();
    }
};
