<?php

namespace App\Enums;

enum TeacherAssignmentType: string
{
    case ClassTeacher = 'class_teacher';
    case SubjectTeacher = 'subject_teacher';

    public function label(): string
    {
        return match ($this) {
            self::ClassTeacher => 'Class Teacher',
            self::SubjectTeacher => 'Subject Teacher',
        };
    }
}
