<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthEvent: string
{
    case UserRegistered = 'user.registered';
    case PasswordReset = 'auth.password_reset';
    case TwoFactorCode = 'auth.two_factor_code';
}
