<?php

namespace App\Enums;

enum InterviewMode: string
{
    case Physical = 'physical';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::Physical => 'Physical (in person)',
            self::Online => 'Online',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Physical => 'fa-building',
            self::Online => 'fa-video',
        };
    }
}
