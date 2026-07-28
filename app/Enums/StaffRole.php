<?php

namespace App\Enums;

enum StaffRole: string
{
    case Teacher = 'teacher';
    case Administrator = 'administrator';
    case Management = 'management';
    case SupportStaff = 'support_staff';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Teacher => 'Teacher',
            self::Administrator => 'Administrator',
            self::Management => 'Management',
            self::SupportStaff => 'Support Staff',
            self::Other => 'Other',
        };
    }
}
