<?php

namespace App\Services;

use App\Models\School;

class DefaultAcademicStructure
{
    /**
     * A sensible starting point covering the standard Nigerian education
     * structure. Schools can rename, remove, or add to these from the
     * Academics screen - not every school needs every level.
     *
     * @var array<string, list<string>>
     */
    private const LEVELS = [
        'Kindergarten/Creche' => ['Creche', 'KG 1', 'KG 2'],
        'Nursery' => ['Nursery 1', 'Nursery 2', 'Nursery 3'],
        'Lower Primary' => ['Primary 1', 'Primary 2', 'Primary 3'],
        'Upper Primary' => ['Primary 4', 'Primary 5', 'Primary 6'],
        'Junior Secondary School' => ['JSS 1', 'JSS 2', 'JSS 3'],
        'Senior Secondary School' => ['SS 1', 'SS 2', 'SS 3'],
    ];

    public static function seedFor(School $school): void
    {
        if ($school->academicLevels()->exists()) {
            return;
        }

        $levelSort = 0;

        foreach (self::LEVELS as $levelName => $classNames) {
            $level = $school->academicLevels()->create([
                'name' => $levelName,
                'sort_order' => $levelSort++,
            ]);

            $classSort = 0;

            foreach ($classNames as $className) {
                $level->classes()->create([
                    'school_id' => $school->id,
                    'name' => $className,
                    'sort_order' => $classSort++,
                ]);
            }
        }
    }
}
