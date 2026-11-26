<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PasswordResetLinkRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
        public string $frontendBase = '',
    ) {}
}
