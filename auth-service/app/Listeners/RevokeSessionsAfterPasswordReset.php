<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Contracts\TokenService;
use Illuminate\Auth\Events\PasswordReset;

class RevokeSessionsAfterPasswordReset
{
    public function __construct(private readonly TokenService $auth) {}

    public function handle(PasswordReset $event): void
    {
        $this->auth->revokeAllForUser((int) $event->user->getAuthIdentifier());
    }
}
