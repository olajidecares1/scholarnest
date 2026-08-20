<?php

namespace App\Enums;

enum ResultCheckingPinStatus: string
{
    case Active = 'active';
    case Exhausted = 'exhausted';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Exhausted => 'Exhausted',
            self::Revoked => 'Revoked',
        };
    }
}
