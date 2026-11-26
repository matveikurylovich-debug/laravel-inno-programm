<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Description('Аннулирует все ранее выданные ссылки сброса пароля')]
#[Signature('auth:invalidate-password-resets')]
class InvalidatePasswordResetsCommand extends Command
{
    public function handle(): int
    {
        $deleted = DB::table('password_reset_tokens')->delete();

        $this->info("Deleted {$deleted} password reset tokens.");

        return self::SUCCESS;
    }
}
