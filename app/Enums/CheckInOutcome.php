<?php

namespace App\Enums;

/**
 * What became of a scan.
 *
 * Refusals are stored, not thrown away. A pupil scanning the poster from the
 * bus stop, or a photograph of the poster being scanned from a bedroom, is
 * exactly what the school wants to be able to see, and a refusal that left no
 * trace would be invisible.
 */
enum CheckInOutcome: string
{
    case Recorded = 'recorded';
    case Duplicate = 'duplicate';
    case OutOfRange = 'out_of_range';
    case NoLocation = 'no_location';
    case NotAllowed = 'not_allowed';

    public function wasRecorded(): bool
    {
        return $this === self::Recorded;
    }

    public function label(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded',
            self::Duplicate => 'Already recorded',
            self::OutOfRange => 'Too far from school',
            self::NoLocation => 'No location from the phone',
            self::NotAllowed => 'Not allowed to check in',
        };
    }

    /**
     * What the person holding the phone is told. Written for them, not for the
     * office: it says what happened and what to do about it.
     */
    public function message(): string
    {
        return match ($this) {
            self::Recorded => 'Recorded.',
            self::Duplicate => 'That scan was already recorded.',
            self::OutOfRange => 'You are too far from the school for this to count. Scan the poster at the gate.',
            self::NoLocation => 'Your phone did not give a location, so this cannot be counted. Allow location for this site and scan again.',
            self::NotAllowed => 'Self check-in is not switched on for you at this school.',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Recorded => 'bg-green-100 text-green-700',
            self::Duplicate => 'bg-gray-100 text-gray-700',
            self::OutOfRange => 'bg-red-100 text-red-700',
            self::NoLocation => 'bg-amber-100 text-amber-700',
            self::NotAllowed => 'bg-red-100 text-red-700',
        };
    }
}
