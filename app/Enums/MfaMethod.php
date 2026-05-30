<?php

declare(strict_types=1);

namespace App\Enums;

enum MfaMethod: string
{
    case Totp = 'totp';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Totp => 'Authenticator App (TOTP)',
            self::Email => 'Email OTP',
        };
    }
}
