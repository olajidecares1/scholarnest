<?php

namespace App\Enums;

enum CbtTestStatus: string
{
    case Draft = 'draft';
    case Locked = 'locked';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Locked => 'Locked',
            self::Published => 'Published',
            self::Archived => 'Archived',
        };
    }
}
