<?php

namespace App\Enums;

/**
 * How a student/pupil record was created.
 */
enum RegistrationSource: string
{
    case Single = 'single';
    case Bulk = 'bulk';

    public function label(): string
    {
        return match ($this) {
            self::Single => 'Single Registration',
            self::Bulk => 'Bulk Registration',
        };
    }

    /**
     * The label for a record that may predate the column.
     */
    public static function labelFor(?self $source): string
    {
        return $source?->label() ?? 'Not recorded (registered before this was tracked)';
    }
}
