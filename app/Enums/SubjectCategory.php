<?php

namespace App\Enums;

enum SubjectCategory: string
{
    case Science = 'science';
    case Arts = 'arts';
    case Commercial = 'commercial';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Science => 'Science',
            self::Arts => 'Arts',
            self::Commercial => 'Commercial',
            self::General => 'General',
        };
    }
}
