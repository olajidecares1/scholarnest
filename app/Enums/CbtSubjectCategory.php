<?php

namespace App\Enums;

enum CbtSubjectCategory: string
{
    case Science = 'science';
    case Arts = 'arts';
    case Commercial = 'commercial';
    case Jss = 'jss';
    case General = 'general';

    public function label(): string
    {
        return match ($this) {
            self::Science => 'Science',
            self::Arts => 'Arts',
            self::Commercial => 'Commercial',
            self::Jss => 'Junior Secondary School',
            self::General => 'General',
        };
    }
}
