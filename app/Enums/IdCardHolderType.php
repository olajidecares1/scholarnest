<?php

namespace App\Enums;

enum IdCardHolderType: string
{
    case Student = 'student';
    case TeachingStaff = 'teaching_staff';
    case NonTeachingStaff = 'non_teaching_staff';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::TeachingStaff => 'Teaching Staff',
            self::NonTeachingStaff => 'Non-Teaching Staff',
        };
    }
}
