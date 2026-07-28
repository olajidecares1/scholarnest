<?php

namespace App\Enums;

enum SubmissionStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Submitted = 'submitted';
    case Late = 'late';
    case Graded = 'graded';

    public function label(): string
    {
        return match ($this) {
            self::NotSubmitted => 'Not Submitted',
            self::Submitted => 'Submitted',
            self::Late => 'Late',
            self::Graded => 'Graded',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::NotSubmitted => 'bg-gray-100 text-gray-600',
            self::Submitted => 'bg-blue-100 text-blue-700',
            self::Late => 'bg-amber-100 text-amber-700',
            self::Graded => 'bg-green-100 text-green-700',
        };
    }
}
