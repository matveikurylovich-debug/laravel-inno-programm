<?php

namespace App\Listeners;

use App\Contracts\AuthService;
use Illuminate\Auth\Events\PasswordReset;

class RevokeSessionsAfterPasswordReset
{
    public function __construct(private readonly AuthService $auth) {}

    public function handle(PasswordReset $event): void
    {
        $this->auth->revokeAllForUser((int) $event->user->getAuthIdentifier());
    }
}
