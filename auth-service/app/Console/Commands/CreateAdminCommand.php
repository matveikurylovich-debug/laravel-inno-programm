<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Description('Создаёт или обновляет первого администратора')]
#[Signature('auth:create-admin {email} {--password=}')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $email = User::normalizeEmail((string) $this->argument('email'));
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
