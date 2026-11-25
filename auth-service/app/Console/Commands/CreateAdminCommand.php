<?php

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminCommand extends Command
{
    protected $signature = 'auth:create-admin {email} {--password=}';

    protected $description = 'Создаёт или обновляет первого администратора';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $password = $this->option('password');
        $generated = false;

        if (! is_string($password) || $password === '') {
            if ($this->input->isInteractive()) {
                $password = (string) $this->secret('Пароль');
            }
            if (! is_string($password) || $password === '') {
                $password = Str::password(16);
                $generated = true;
            }
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        if (! $user->exists) {
            $user->name = 'Admin';
        }
        $user->password = $password;
        $user->save();
        $user->syncRoles([RoleName::Admin]);

        if ($generated) {
            $this->line($password);
        }

        $this->info("Администратор {$email} готов.");

        return self::SUCCESS;
    }
}
