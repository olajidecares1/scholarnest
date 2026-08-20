<?php

namespace App\Services;

use App\Enums\ClassStream;
use App\Models\School;

class DefaultAcademicStructure
{
    /**
     * A sensible starting point covering the standard Nigerian education
     * structure. Schools can rename, remove, or add to these from the
     * Academics screen - not every school needs every level. Each class is
     * either a plain name, or [name, stream] for SSS's three streams - a
     * student's stream is which class they're actually in (e.g. "SS 1
     * Science" is a distinct class from "SS 1 Art"), matching how
     * Student::class_name already treats classes as plain, freestanding
     * names throughout the rest of the app.
     *
     * @var array<string, list<string|array{0: string, 1: ClassStream}>>
     */
    private const LEVELS = [
        'KG/Creche' => ['Creche', 'KG 1', 'KG 2'],
        'Nursery' => ['Nursery 1', 'Nursery 2', 'Nursery 3'],
        'Lower Primary' => ['Primary 1', 'Primary 2', 'Primary 3'],
        'Upper Primary' => ['Primary 4', 'Primary 5', 'Primary 6'],
        'JSS' => ['JSS 1', 'JSS 2', 'JSS 3'],
        'SSS' => [
            ['SS 1 Science', ClassStream::Science],
            ['SS 1 Art', ClassStream::Art],
            ['SS 1 Commercial', ClassStream::Commercial],
            ['SS 2 Science', ClassStream::Science],
            ['SS 2 Art', ClassStream::Art],
            ['SS 2 Commercial', ClassStream::Commercial],
            ['SS 3 Science', ClassStream::Science],
            ['SS 3 Art', ClassStream::Art],
            ['SS 3 Commercial', ClassStream::Commercial],
        ],
    ];

    /**
     * The admission-number level code seeded for each default level - the
     * School Admin can rename these per level from the Academics screen
     * once they've been created, same as level names.
     *
     * @var array<string, string>
     */
    private const LEVEL_CODES = [
        'KG/Creche' => 'CRE',
        'Nursery' => 'NUR',
        'Lower Primary' => 'PRY',
        'Upper Primary' => 'PRY',
        'JSS' => 'JUR',
        'SSS' => 'SEN',
    ];

    public static function seedFor(School $school): void
    {
        if ($school->academicLevels()->exists()) {
            return;
        }

        $levelSort = 0;

        foreach (self::LEVELS as $levelName => $classes) {
            $level = $school->academicLevels()->create([
                'name' => $levelName,
                'code' => self::LEVEL_CODES[$levelName] ?? null,
                'sort_order' => $levelSort++,
            ]);

            $classSort = 0;

            foreach ($classes as $class) {
                [$className, $stream] = is_array($class) ? $class : [$class, null];

                $level->classes()->create([
                    'school_id' => $school->id,
                    'name' => $className,
                    'stream' => $stream,
                    'sort_order' => $classSort++,
                ]);
            }
        }
    }
}
