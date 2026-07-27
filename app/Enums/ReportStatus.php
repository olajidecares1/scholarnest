<?php

namespace App\Enums;

enum ReportStatus: string
{
    case New = 'new';
    case Reviewing = 'reviewing';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Reviewing => 'Reviewing',
            self::Resolved => 'Resolved',
        };
    }
}
