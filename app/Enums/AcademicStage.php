<?php

namespace App\Enums;

enum AcademicStage: string
{
    case EarlyYears = 'early_years';
    case Primary = 'primary';
    case JuniorSecondary = 'junior_secondary';
    case SeniorSecondary = 'senior_secondary';

    public function label(): string
    {
        return match ($this) {
            self::EarlyYears => 'Nursery / Early Years',
            self::Primary => 'Primary',
            self::JuniorSecondary => 'Junior Secondary',
            self::SeniorSecondary => 'Senior Secondary',
        };
    }
}
