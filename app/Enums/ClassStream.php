<?php

namespace App\Enums;

enum ClassStream: string
{
    case Science = 'science';
    case Art = 'art';
    case Commercial = 'commercial';

    public function label(): string
    {
        return match ($this) {
            self::Science => 'Science',
            self::Art => 'Art',
            self::Commercial => 'Commercial',
        };
    }
}
