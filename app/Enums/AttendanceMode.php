<?php

namespace App\Enums;

/**
 * How a school takes its pupils' register.
 *
 * Manual is the register that has always existed: a teacher or the office
 * ticks a class off by hand. Qr is the poster by the gate, scanned by each
 * pupil's own phone on the way in and on the way out.
 *
 * The choice is the school's, per school, and it only governs whether a PUPIL
 * may check themselves in. Marking by hand never stops working, on either
 * setting, because a register that cannot be corrected is not a register.
 */
enum AttendanceMode: string
{
    case Manual = 'manual';
    case Qr = 'qr';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Marked by a teacher',
            self::Qr => 'Pupils scan the QR poster',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Manual => 'The class teacher or the office ticks the class off, the way it works today.',
            self::Qr => 'Each pupil scans the poster at the gate on arrival and again on the way home. Staff can still correct any row by hand.',
        };
    }
}
