<?php

namespace App\Enums;

enum EventAudience: string
{
    case Everyone = 'everyone';
    case Students = 'students';
    case Staff = 'staff';
    case Parents = 'parents';

    public function label(): string
    {
        return match ($this) {
            self::Everyone => 'Everyone',
            self::Students => 'Students',
            self::Staff => 'Staff',
            self::Parents => 'Parents',
        };
    }
}
