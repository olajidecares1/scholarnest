<?php

namespace App\Enums;

enum HostelGender: string
{
    case Male = 'male';
    case Female = 'female';
    case Mixed = 'mixed';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Boys',
            self::Female => 'Girls',
            self::Mixed => 'Mixed',
        };
    }
}
