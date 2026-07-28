<?php

namespace App\Enums;

enum ExamTerm: string
{
    case First = 'first';
    case Second = 'second';
    case Third = 'third';

    public function label(): string
    {
        return match ($this) {
            self::First => 'First Term',
            self::Second => 'Second Term',
            self::Third => 'Third Term',
        };
    }
}
