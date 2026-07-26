<?php

namespace App\Enums;

enum BillingCycle: string
{
    case PerStudentPerTerm = 'per_student_per_term';
    case Monthly = 'monthly';
    case PerTerm = 'per_term';

    public function label(): string
    {
        return match ($this) {
            self::PerStudentPerTerm => 'Per Student / Per Term',
            self::Monthly => 'Monthly',
            self::PerTerm => 'Per Term',
        };
    }
}
