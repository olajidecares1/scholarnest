<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case SchoolAdmin = 'school_admin';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'AkademicNest Team',
            self::SchoolAdmin => 'School Admin',
        };
    }
}
