<?php

namespace App\Services;

use App\Enums\SubjectCategory;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectOffering;
use Illuminate\Support\Collection;

/**
 * The subjects every class on the platform offers by default, from
 * Reception to SS 3, in the order they are printed on the result sheet.
 *
 * The lists live in config/subjects.php and nowhere else. Every school gets
 * them for its classes when it registers, a class added later starts with
 * its list, and `php artisan subjects:backfill-defaults` brings schools
 * already on the platform in line. The offerings are marked is_default, and
 * a School Admin cannot remove them; they can still add subjects of their
 * own.
 *
 * Which list a class takes is its "group" (reception, nursery_1, primary,
 * jss_1, sss_science, ...), worked out from the class's name, the level it
 * is filed under and, for SS classes, its department. See groupFor().
 */
class DefaultClassSubjects
{
    // ------------------------------------------------------------------
    // The lists
    // ------------------------------------------------------------------

    /**
     * Every group, with its full subject list (an SS department's list
     * starts with the general SS subjects).
     *
     * @return array<string, array{label: string, subjects: list<string>}>
     */
    public static function groups(): array
    {
        $configured = (array) config('subjects.class_groups', []);
        $groups = [];

        foreach ($configured as $key => $group) {
            $subjects = (array) ($group['subjects'] ?? []);

            if (! empty($group['includes']) && isset($configured[$group['includes']])) {
                $subjects = [...(array) ($configured[$group['includes']]['subjects'] ?? []), ...$subjects];
            }

            $groups[(string) $key] = [
                'label' => (string) ($group['label'] ?? $key),
                'subjects' => array_values(array_unique(array_map('strval', $subjects))),
            ];
        }

        return $groups;
    }

    /**
     * A group's subjects in result-sheet order; empty for no group.
     *
     * @return list<string>
     */
    public static function namesForGroup(?string $group): array
    {
        return $group === null ? [] : (self::groups()[$group]['subjects'] ?? []);
    }

    public static function labelForGroup(?string $group): ?string
    {
        return $group === null ? null : (self::groups()[$group]['label'] ?? null);
    }

    /**
     * Name => ['category' => SubjectCategory, 'aliases' => list<string>] for
     * every subject on any list.
     *
     * @return array<string, array{category: SubjectCategory, aliases: list<string>}>
     */
    public static function definitions(): array
    {
        $configured = (array) config('subjects.subjects', []);
        $definitions = [];

        foreach (self::groups() as $group) {
            foreach ($group['subjects'] as $name) {
                $settings = (array) ($configured[$name] ?? []);

                $definitions[$name] ??= [
                    'category' => SubjectCategory::tryFrom((string) ($settings['category'] ?? '')) ?? SubjectCategory::General,
                    'aliases' => array_values(array_map('strval', (array) ($settings['aliases'] ?? []))),
                ];
            }
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
        $name = preg_replace('/\s*,\s*/u', ', ', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Which of a group's default subjects a name means: its own name first,
     * then any of the aliases in config ("Maths" => "Mathematics"). Null
     * when the name is not one of that group's defaults.
     */
    public static function matchInGroup(?string $subjectName, ?string $group): ?string
    {
        $wanted = self::normalize($subjectName);

        if ($wanted === '' || $group === null) {
            return null;
        }

        $names = self::namesForGroup($group);

        foreach ($names as $name) {
            if (self::normalize($name) === $wanted) {
                return $name;
            }
        }

        $definitions = self::definitions();

        foreach ($names as $name) {
            foreach ($definitions[$name]['aliases'] ?? [] as $alias) {
                if (self::normalize($alias) === $wanted) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Which default subject, on any list, a name means: a default's own
     * name first, then the aliases. Null when it is none of them.
     */
    public static function defaultNameFor(?string $subjectName): ?string
    {
        $wanted = self::normalize($subjectName);
        $definitions = self::definitions();

        foreach (array_keys($definitions) as $name) {
            if (self::normalize($name) === $wanted) {
                return $name;
            }
        }

        foreach ($definitions as $name => $definition) {
            foreach ($definition['aliases'] as $alias) {
                if (self::normalize($alias) === $wanted) {
                    return $name;
                }
            }
        }

        return null;
    }

    /**
     * Whether two names are the same subject: the same once normalised, or
     * the same default subject of the class's group ("Maths" and
     * "Mathematics").
     */
    public static function sameSubject(?string $a, ?string $b, ?string $group): bool
    {
        if (self::normalize($a) === self::normalize($b)) {
            return true;
        }

        $match = self::matchInGroup($a, $group);

        return $match !== null && $match === self::matchInGroup($b, $group);
    }

    /**
     * Whether a name is, or was, a platform default subject anywhere: a
     * subject on any list, any of its aliases, or a retired default.
     */
    public static function isPlatformSubject(?string $name): bool
    {
        return self::defaultNameFor($name) !== null || self::isRetiredName($name);
    }

    public static function isRetiredName(?string $name): bool
    {
        $normalized = self::normalize($name);

        return collect((array) config('subjects.retired_defaults', []))
            ->contains(fn ($retired) => self::normalize((string) $retired) === $normalized);
    }

    // ------------------------------------------------------------------
    // Which list a class takes
    // ------------------------------------------------------------------

    /**
     * The group a class belongs to, from its name, the name of the level it
     * is filed under and (for SS) its department. Null for a class the
     * platform does not recognise; such a class gets no default subjects.
     *
     * The class name is read first, so "Nursery 2" filed under a level
     * called "Early Years" is still Nursery 2; the level decides only when
     * the name says nothing ("Year 7" under "JSS").
     */
    public static function groupFor(?string $className, ?string $levelName = null, ?string $stream = null): ?string
    {
        $name = self::normalize($className);
        $level = self::normalize($levelName);

        foreach ([$name, $level] as $text) {
            if ($text === '') {
                continue;
            }

            // From the level, a class number can still only come from the
            // class's own name ("Year 8" under "JSS").
            $group = self::groupFromText($text, $name, $stream);

            if ($group !== null) {
                return $group;
            }
        }

        return null;
    }

    /**
     * The group of a class model, using its level and department.
     */
    public static function groupForClass(SchoolClass $class): ?string
    {
        return self::groupFor(
            $class->name,
            $class->academicLevel?->name,
            $class->stream?->value,
        );
    }

    /**
     * The group of a school's class by name. A name the school has not set
     * up as a class (e.g. one imported with students) is read from the name
     * alone.
     */
    public static function groupForSchoolClassName(int $schoolId, string $className): ?string
    {
        $class = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('name', $className)
            ->with('academicLevel')
            ->first();

        return $class ? self::groupForClass($class) : self::groupFor($className);
    }

    private static function groupFromText(string $text, string $className, ?string $stream): ?string
    {
        // Junior Secondary: "JSS 1", "J.S.S 2", "JS 3", "Junior Secondary",
        // "Year 7" under a JSS level.
        // "Basic 7" to "Basic 9" are the JSS years under the 9-year basic
        // education numbering.
        if (preg_match('/^(j ?s ?s(?![a-z])|j ?s ?\d|junior (secondary|high)|basic ?[7-9](?!\d))/u', $text)) {
            preg_match('/(\d+)/', $className, $digits);
            $number = (int) ($digits[1] ?? 0);

            // JSS 2 and 3 (Year 8 / 9, Basic 8 / 9) share a list.
            return in_array($number, [2, 3, 8, 9], true) ? 'jss_2_3' : 'jss_1';
        }

        // Senior Secondary: "SS 1 Science", "SSS 2", "S.S.3", "Senior Secondary".
        if (preg_match('/^(s ?s ?s? ?\d|sss?(?![a-z])|senior (secondary|high))/u', $text)) {
            $department = $stream ?: self::departmentIn($className) ?: self::departmentIn($text);

            return match ($department) {
                'science' => 'sss_science',
                'art' => 'sss_art',
                'commercial' => 'sss_commercial',
                default => 'sss',
            };
        }

        // Pre-nursery and the reception year take the Reception list.
        if (preg_match('/(reception|kindergarten|^kg|creche|crèche|play ?(group|school)|toddler|pre[ -]?(nursery|school|kg))/u', $text)) {
            return 'reception';
        }

        // Nursery 1, Nursery 2; Nursery 3 takes the Nursery 2 list.
        if (str_contains($text, 'nursery')) {
            preg_match('/(\d+)/', $className, $digits);
            $number = (int) ($digits[1] ?? 1);

            return $number >= 2 ? 'nursery_2' : 'nursery_1';
        }

        // Primary 1–6: "Primary 4", "Pry 2", "Basic 3", "Grade 1", "Year 5".
        // Primary 4 and 5 have a list of their own; the class number comes
        // from the class's own name ("Year 4" under a "Primary" level).
        if (preg_match('/(primary|^pry|^basic ?[1-6](?!\d)|^grade ?\d|^year ?[1-6](?!\d)|^class ?\d)/u', $text)) {
            preg_match('/(\d+)/', $className, $digits);

            return in_array((int) ($digits[1] ?? 0), [4, 5], true) ? 'primary_4_5' : 'primary';
        }

        return null;
    }

    private static function departmentIn(string $text): ?string
    {
        return match (true) {
            (bool) preg_match('/\bsciences?\b/u', $text) => 'science',
            (bool) preg_match('/\b(art|arts|humanities)\b/u', $text) => 'art',
            (bool) preg_match('/\b(commercial|commerce|business)\b/u', $text) => 'commercial',
            default => null,
        };
    }

    // ------------------------------------------------------------------
    // Putting the defaults on classes
    // ------------------------------------------------------------------

    /**
     * Every default subject as a catalogue entry, keyed by its default
     * name, created on first use so this works whether or not the catalogue
     * seeder has run on this database.
     *
     * A subject the catalogue already holds under one of its aliases
     * ("Christian Religious Studies" for CRS) is used as it is, never added
     * again. An alias that is itself the name of another default ("English"
     * for "English Language") is not used here, so each default keeps its
     * own catalogue entry.
     *
     * @return Collection<string, Subject>
     */
    public static function catalogueSubjects(): Collection
    {
        $catalogue = Subject::query()->orderBy('id')->get();
        $definitions = self::definitions();
        $defaultNames = collect(array_keys($definitions))->map(fn ($name) => self::normalize($name));

        return collect($definitions)->map(function (array $definition, string $name) use (&$catalogue, $defaultNames) {
            $spellings = collect([$name, ...$definition['aliases']])
                ->map(fn ($spelling) => self::normalize($spelling))
                ->filter(fn ($spelling, $index) => $index === 0 || ! $defaultNames->contains($spelling));

            foreach ($spellings as $spelling) {
                $match = $catalogue->first(fn (Subject $subject) => self::normalize($subject->name) === $spelling);

                if ($match) {
                    return $match;
                }
            }

            $subject = Subject::create(['name' => $name, 'category' => $definition['category']]);
            $catalogue->push($subject);

            return $subject;
        });
    }

    /**
     * Gives every class in the school its default subjects. Safe to run any
     * number of times. Returns how many subjects were added.
     */
    public static function ensureFor(School $school, ?Collection $catalogueSubjects = null): int
    {
        $classes = $school->schoolClasses()->with('academicLevel')->get();

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
     *  - marks the class's default offerings is_default;
     *  - takes off a subject the platform put on the class that is no
     *    longer on its list (the list was edited). No score is lost: scores
     *    belong to the examination, not to this list;
     *  - brings this session's examinations for the class in line, so the
     *    subjects reach the score sheets.
     *
     * Nothing a school added itself is removed.
     */
    public static function ensureForClass(SchoolClass $class, ?Collection $catalogueSubjects = null): int
    {
        $class->loadMissing('academicLevel');
        $group = self::groupForClass($class);

        $offerings = SubjectOffering::with('subject')
            ->where('school_id', $class->school_id)
            ->where('class_name', $class->name)
            ->get();

        $added = 0;
        $claimed = collect();

        if ($group !== null) {
            $catalogueSubjects ??= self::catalogueSubjects();

            foreach (self::namesForGroup($group) as $defaultName) {
                $existing = $offerings->first(fn (SubjectOffering $offering) => ! $claimed->contains($offering->id)
                    && $offering->subject
                    && self::matchInGroup($offering->subject->name, $group) === $defaultName);

                if ($existing) {
                    $claimed->push($existing->id);

                    if (! $existing->is_default) {
                        $existing->forceFill(['is_default' => true])->save();
                    }

                    continue;
                }

                $subject = $catalogueSubjects->get($defaultName);

                if (! $subject) {
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
        }

        // A subject the platform put on this class that is not on its list
        // any more comes off. A school's own subjects are not touched.
        SubjectOffering::where('school_id', $class->school_id)
            ->where('class_name', $class->name)
            ->where('is_default', true)
            ->whereNotIn('id', $claimed)
            ->delete();

        if ($class->school) {
            ExaminationSubjectSync::syncClass($class->school, $class->name);
        }

        return $added;
    }

    /**
     * Every school on the platform, as `subjects:backfill-defaults` runs it.
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

    // ------------------------------------------------------------------
    // Result-sheet order
    // ------------------------------------------------------------------

    /**
     * Items put in result-sheet order for a group: its default subjects
     * first, in the order in config, then anything else A to Z. With no
     * group, plain A to Z. $name picks the subject name out of each item.
     *
     * @template TItem
     *
     * @param  iterable<TItem>  $items
     * @param  callable(TItem): ?string  $name
     * @return Collection<int, TItem>
     */
    public static function sortByGroup(iterable $items, ?string $group, callable $name): Collection
    {
        $position = array_flip(self::namesForGroup($group));
        $last = count($position);

        $key = function ($item) use ($name, $group, $position, $last): array {
            $label = (string) $name($item);
            $default = self::matchInGroup($label, $group);

            return [$default !== null ? $position[$default] : $last, self::normalize($label)];
        };

        return collect($items)
            ->sort(fn ($a, $b) => $key($a) <=> $key($b))
            ->values();
    }

    /**
     * Same, for a school's class by name.
     *
     * @template TItem
     *
     * @param  iterable<TItem>  $items
     * @param  callable(TItem): ?string  $name
     * @return Collection<int, TItem>
     */
    public static function sortForClass(iterable $items, int $schoolId, string $className, callable $name): Collection
    {
        return self::sortByGroup($items, self::groupForSchoolClassName($schoolId, $className), $name);
    }

    /**
     * A class's subject names in result-sheet order.
     *
     * @param  Collection<int, string>  $names
     * @return Collection<int, string>
     */
    public static function orderForClass(Collection $names, int $schoolId, string $className): Collection
    {
        return self::sortForClass($names, $schoolId, $className, fn ($name) => (string) $name);
    }
}
