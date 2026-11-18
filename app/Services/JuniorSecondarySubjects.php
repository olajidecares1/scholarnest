<?php

namespace App\Services;

use App\Enums\SubjectCategory;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectOffering;
use Illuminate\Support\Collection;

/**
 * The 17 subjects every Junior Secondary School (JSS) class offers by
 * default, in the order they are printed on the JSS result sheet. Every
 * school gets them ticked for its JSS classes when it registers, and any
 * JSS class added later starts with them too. A school can still untick a
 * subject it does not run from the Class Subjects page.
 */
class JuniorSecondarySubjects
{
    /**
     * Subject name => the catalogue category it is filed under if it is not
     * in the catalogue yet. A subject already in the catalogue keeps its
     * existing category.
     *
     * @var array<string, SubjectCategory>
     */
    public const SUBJECTS = [
        'English Language' => SubjectCategory::General,
        'Mathematics' => SubjectCategory::General,
        'Basic Science' => SubjectCategory::Science,
        'Basic Technology' => SubjectCategory::Science,
        'Agricultural Science' => SubjectCategory::General,
        'Home Economics' => SubjectCategory::General,
        'Social Studies' => SubjectCategory::General,
        'PHE' => SubjectCategory::Science,
        'Computer Science' => SubjectCategory::Science,
        'Yoruba' => SubjectCategory::Arts,
        'French' => SubjectCategory::Arts,
        'Civic Education' => SubjectCategory::General,
        'Business Studies' => SubjectCategory::Commercial,
        'CRS/IRS' => SubjectCategory::Arts,
        'History' => SubjectCategory::Arts,
        'Music' => SubjectCategory::Arts,
        'Cultural & Creative Art' => SubjectCategory::Arts,
    ];

    /**
     * The 17 subject names in result-sheet order.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::SUBJECTS);
    }

    /**
     * Whether a class is a Junior Secondary class: "JSS 1", "J.S.S. 2",
     * "JSS 3 Gold", "Junior Secondary 1", or any class filed under a level
     * named that way (e.g. a school that renamed its classes "Year 7").
     */
    public static function isJuniorClass(?string $className, ?string $levelName = null): bool
    {
        $pattern = '/^\s*(j\.?\s*s\.?\s*s(?![a-z])|j\.?\s*s\.?\s*\d|junior\s+(secondary|high))/i';

        return ($className !== null && preg_match($pattern, $className) === 1)
            || ($levelName !== null && preg_match($pattern, $levelName) === 1);
    }

    /**
     * The 17 catalogue subjects, created on first use so this works whether
     * or not the catalogue seeder has run on this database.
     *
     * @return Collection<int, Subject>
     */
    public static function catalogueSubjects(): Collection
    {
        return collect(self::SUBJECTS)
            ->map(fn (SubjectCategory $category, string $name) => Subject::firstOrCreate(['name' => $name], ['category' => $category]))
            ->values();
    }

    /**
     * Ticks the 17 subjects for every JSS class in the school. By default
     * only classes with no subjects set up yet are filled, so a school's own
     * choices are never overwritten; pass $addMissing to also add whichever
     * of the 17 a class is missing. Returns how many subjects were added.
     */
    public static function ensureFor(School $school, bool $addMissing = false): int
    {
        $added = 0;

        $school->schoolClasses()->with('academicLevel')->get()
            ->filter(fn (SchoolClass $class) => self::isJuniorClass($class->name, $class->academicLevel?->name))
            ->each(function (SchoolClass $class) use (&$added, $addMissing) {
                $added += self::ensureForClass($class, $addMissing);
            });

        return $added;
    }

    /**
     * Same as ensureFor(), for one class.
     */
    public static function ensureForClass(SchoolClass $class, bool $addMissing = false): int
    {
        if (! self::isJuniorClass($class->name, $class->academicLevel?->name)) {
            return 0;
        }

        $existing = SubjectOffering::where('school_id', $class->school_id)
            ->where('class_name', $class->name)
            ->pluck('subject_id');

        if ($existing->isNotEmpty() && ! $addMissing) {
            return 0;
        }

        $added = 0;

        foreach (self::catalogueSubjects() as $subject) {
            if ($existing->contains($subject->id)) {
                continue;
            }

            SubjectOffering::firstOrCreate([
                'school_id' => $class->school_id,
                'class_name' => $class->name,
                'subject_id' => $subject->id,
            ]);
            $added++;
        }

        return $added;
    }

    /**
     * Puts a JSS class's subject names in result-sheet order: the 17 first,
     * in the order above, then any extra subjects the school added, A to Z.
     * Other classes keep plain alphabetical order.
     *
     * @param  Collection<int, string>  $names
     * @return Collection<int, string>
     */
    public static function order(Collection $names, string $className, ?string $levelName = null): Collection
    {
        $names = $names->sort(fn ($a, $b) => strcasecmp((string) $a, (string) $b))->values();

        if (! self::isJuniorClass($className, $levelName)) {
            return $names;
        }

        $position = array_flip(array_map('mb_strtolower', self::names()));
        $last = count($position);

        return $names
            ->sortBy(fn ($name) => [$position[mb_strtolower((string) $name)] ?? $last, mb_strtolower((string) $name)])
            ->values();
    }
}
