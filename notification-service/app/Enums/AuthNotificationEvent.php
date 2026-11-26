<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthNotificationEvent: string
{
    case UserRegistered = 'user.registered';
    case PasswordReset = 'auth.password_reset';
    case TwoFactorCode = 'auth.two_factor_code';

    public function containsSecret(): bool
    {
        return match ($this) {
            self::PasswordReset, self::TwoFactorCode => true,
            self::UserRegistered => false,
        };
    }
}
