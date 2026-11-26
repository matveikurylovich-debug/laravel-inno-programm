<?php

declare(strict_types=1);

namespace App\Support;

enum TwoFactorCacheKey: string
{
    public const TTL_MINUTES = 5;

    case Challenge = '2fa_challenge_';
    case Attempts = '2fa_attempts_';
    case Resend = '2fa_resend_';
    case Locked = '2fa_locked_';
    case User = '2fa_user_';

    public function with(int|string $suffix): string
    {
        return $this->value.$suffix;
    }
}
