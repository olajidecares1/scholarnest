<?php

namespace App\Enums;

/**
 * Which end of the day a scan is.
 *
 * Nobody chooses this, and the poster carries no "in" and "out" side to stand
 * at: the first accepted scan of a person's day is their arrival, and every
 * later one moves their departure. See App\Services\Attendance\CheckInService.
 */
enum CheckInKind: string
{
    case Arrival = 'arrival';
    case Departure = 'departure';

    public function label(): string
    {
        return match ($this) {
            self::Arrival => 'Arrival',
            self::Departure => 'Departure',
        };
    }
}
