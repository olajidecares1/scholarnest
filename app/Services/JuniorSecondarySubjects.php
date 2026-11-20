<?php

namespace App\Services;

use App\Enums\SubjectCategory;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectOffering;
use Illuminate\Support\Collection;

/**
 * The subjects every Junior Secondary School (JSS 1–3) class offers by
 * default, in the order they are printed on the JSS result sheet.
 *
 * The list itself lives in config/subjects.php and nowhere else. Every
 * school gets these subjects for its JSS classes when it registers, any JSS
 * class added later starts with them, and `php artisan subjects:backfill-jss`
 * brings schools already on the platform in line. The offerings are marked
 * is_default, and a School Admin cannot remove them; they can still add
 * subjects of their own.
 */
class JuniorSecondarySubjects
{
    /**
     * The default subject names in result-sheet order.
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::definitions());
    }

    /**
     * Name => ['category' => SubjectCategory, 'aliases' => list<string>].
     *
     * @return array<string, array{category: SubjectCategory, aliases: list<string>}>
     */
    public static function definitions(): array
    {
        $definitions = [];

        foreach ((array) config('subjects.jss_defaults', []) as $name => $settings) {
            $definitions[(string) $name] = [
                'category' => SubjectCategory::tryFrom((string) ($settings['category'] ?? '')) ?? SubjectCategory::General,
                'aliases' => array_values(array_map('strval', (array) ($settings['aliases'] ?? []))),
            ];
        }

        return $definitions;
    }

    /**
     * A subject name reduced to what matters when comparing two of them:
     * lower case, "&" read as "and", dots dropped, runs of spaces made one.
     * "Cultural & Creative Art", "cultural and  creative art" and
     * "CULTURAL AND CREATIVE ART" all come out the same.
     */
    public static function normalize(?string $name): string
    {
        $name = mb_strtolower((string) $name);
        $name = str_replace('&', ' and ', $name);
        $name = str_replace('.', '', $name);
        $name = preg_replace('/\s*\/\s*/u', '/', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Which default subject a name means, by its own name or any of its
     * aliases ("Physical and Health Education" => "PHE"), or null when the
     * name is not one of the defaults.
     */
    public static function defaultNameFor(?string $name): ?string
    {
        return self::lookup()[self::normalize($name)] ?? null;
    }

    public static function isDefaultName(?string $name): bool
    {
        return self::defaultNameFor($name) !== null;
    }

    /**
     * Whether a name is one of the retired defaults (see config).
     */
    public static function isRetiredName(?string $name): bool
    {
        $normalized = self::normalize($name);

        return collect((array) config('subjects.retired_jss_defaults', []))
            ->contains(fn ($retired) => self::normalize((string) $retired) === $normalized);
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
     * The default subjects as catalogue entries, in result-sheet order,
     * created on first use so this works whether or not the catalogue seeder
     * has run on this database.
     *
     * A subject the catalogue already holds under another spelling is used
     * as it is ("Christian Religious Studies" for CRS), never added again.
     *
     * @return Collection<int, Subject>
     */
    public static function catalogueSubjects(): Collection
    {
        $catalogue = Subject::query()->orderBy('id')->get();

        return collect(self::definitions())
            ->map(function (array $definition, string $name) use ($catalogue) {
                $spellings = collect([$name, ...$definition['aliases']])->map(fn ($spelling) => self::normalize($spelling));

                // The exact name first, then the aliases in the order listed.
                foreach ($spellings as $spelling) {
                    $match = $catalogue->first(fn (Subject $subject) => self::normalize($subject->name) === $spelling);

                    if ($match) {
                        return $match;
                    }
                }

                return Subject::create(['name' => $name, 'category' => $definition['category']]);
            })
            ->values();
    }

    /**
     * Gives every JSS class in the school all the default subjects, marked
     * as defaults. Safe to run any number of times. Returns how many
     * subjects were added.
     */
    public static function ensureFor(School $school, ?Collection $catalogueSubjects = null): int
    {
        $classes = $school->schoolClasses()->with('academicLevel')->get()
            ->filter(fn (SchoolClass $class) => self::isJuniorClass($class->name, $class->academicLevel?->name));

        if ($classes->isEmpty()) {
            return 0;
        }

        $catalogueSubjects ??= self::catalogueSubjects();

        return $classes->sum(fn (SchoolClass $class) => self::ensureForClass($class, $catalogueSubjects));
    }

    /**
     * Same as ensureFor(), for one class:
     *
     *  - adds each default subject the class does not offer yet, unless it
     *    already offers it under another spelling (case, spaces and the
     *    aliases in config all count as the same subject);
     *  - marks the class's default offerings is_default, and clears the mark
     *    from any that are no longer defaults, so the admin can manage them;
     *  - takes retired defaults (the old combined CRS/IRS) off the class.
     *
     * Nothing a school added itself is removed.
     */
    public static function ensureForClass(SchoolClass $class, ?Collection $catalogueSubjects = null): int
    {
        if (! self::isJuniorClass($class->name, $class->academicLevel?->name)) {
            return 0;
        }

        $catalogueSubjects ??= self::catalogueSubjects();

        $offerings = SubjectOffering::with('subject')
            ->where('school_id', $class->school_id)
            ->where('class_name', $class->name)
            ->get();

        // Retired defaults come off first, so they are not counted as
        // anything below.
        $offerings = $offerings->reject(function (SubjectOffering $offering) {
            if ($offering->subject && self::isRetiredName($offering->subject->name)) {
                $offering->delete();

                return true;
            }

            return false;
        });

        $added = 0;
        $claimed = collect();

        foreach ($catalogueSubjects as $subject) {
            $defaultName = self::defaultNameFor($subject->name);

            $existing = $offerings->first(fn (SubjectOffering $offering) => ! $claimed->contains($offering->id)
                && $offering->subject
                && self::defaultNameFor($offering->subject->name) === $defaultName);

            if ($existing) {
                $claimed->push($existing->id);

                if (! $existing->is_default) {
                    $existing->forceFill(['is_default' => true])->save();
                }

                continue;
            }

            $offering = SubjectOffering::firstOrCreate(
                ['school_id' => $class->school_id, 'class_name' => $class->name, 'subject_id' => $subject->id],
                ['is_default' => true],
            );

            if (! $offering->is_default) {
                $offering->forceFill(['is_default' => true])->save();
            }

            $claimed->push($offering->id);
            $added += $offering->wasRecentlyCreated ? 1 : 0;
        }

        // An offering marked default that no longer matches the list (the
        // list in config was edited) becomes the school's own subject.
        SubjectOffering::where('school_id', $class->school_id)
            ->where('class_name', $class->name)
            ->where('is_default', true)
            ->whereNotIn('id', $claimed)
            ->update(['is_default' => false]);

        return $added;
    }

    /**
     * Every school on the platform, as `subjects:backfill-jss` runs it.
     *
     * @return array{schools: int, added: int}
     */
    public static function backfillAll(): array
    {
        $catalogueSubjects = self::catalogueSubjects();
        $schools = 0;
        $added = 0;

        School::query()->chunkById(100, function ($chunk) use (&$schools, &$added, $catalogueSubjects) {
            foreach ($chunk as $school) {
                $schools++;
                $added += self::ensureFor($school, $catalogueSubjects);
            }
        });

        return ['schools' => $schools, 'added' => $added];
    }

    /**
     * Puts a JSS class's subject names in result-sheet order: the defaults
     * first, in the order in config, then any extra subjects the school
     * added, A to Z. Other classes keep plain alphabetical order.
     *
     * @param  Collection<int, string>  $names
     * @return Collection<int, string>
     */
    public static function order(Collection $names, string $className, ?string $levelName = null): Collection
    {
        return self::sortByName($names, $className, $levelName, fn ($name) => (string) $name);
    }

    /**
     * The same order for a list of anything that has a subject name, e.g. an
     * examination's subjects on a report card. $name picks the subject name
     * out of each item.
     *
     * @template TItem
     *
     * @param  iterable<TItem>  $items
     * @param  callable(TItem): ?string  $name
     * @return Collection<int, TItem>
     */
    public static function sortByName(iterable $items, string $className, ?string $levelName, callable $name): Collection
    {
        $isJunior = self::isJuniorClass($className, $levelName);
        $position = array_flip(self::names());
        $last = count($position);

        $key = function ($item) use ($name, $isJunior, $position, $last): array {
            $label = (string) $name($item);
            $default = $isJunior ? self::defaultNameFor($label) : null;

            return [$default !== null ? $position[$default] : ($isJunior ? $last : 0), self::normalize($label)];
        };

        return collect($items)
            ->sort(fn ($a, $b) => $key($a) <=> $key($b))
            ->values();
    }

    /**
     * The name of the level a school's class is filed under, so a class the
     * school renamed (e.g. "Year 7" under the JSS level) still counts as JSS.
     */
    public static function levelNameFor(int $schoolId, string $className): ?string
    {
        return SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('name', $className)
            ->with('academicLevel')
            ->first()
            ?->academicLevel
            ?->name;
    }

    /**
     * Normalized name or alias => default name.
     *
     * @return array<string, string>
     */
    private static function lookup(): array
    {
        $lookup = [];

        foreach (self::definitions() as $name => $definition) {
            foreach ([$name, ...$definition['aliases']] as $spelling) {
                $lookup[self::normalize($spelling)] ??= $name;
            }
        }

        return $lookup;
    }
}
