<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InvalidatePasswordResetsCommand extends Command
{
    protected $signature = 'auth:invalidate-password-resets';

    protected $description = 'Аннулирует все ранее выданные ссылки сброса пароля';

    public function handle(): int
    {
        $deleted = DB::table('password_reset_tokens')->delete();

        $this->info("Deleted {$deleted} password reset tokens.");

        return self::SUCCESS;
    }
}
