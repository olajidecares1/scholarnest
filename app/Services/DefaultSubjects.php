<?php

namespace App\Services;

use App\Enums\ClassStream;
use App\Enums\SubjectCategory;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Subject;
use App\Models\SubjectOffering;
use Illuminate\Support\Collection;

/**
 * The subjects each class offers by default (Reception/KG, Nursery 1,
 * Nursery 2, Primary, JSS 1, JSS 2–3 and SS with its three streams), in the
 * order they are printed on the result sheet.
 *
 * The lists live in config/subjects.php and nowhere else. Every school gets
 * them when it registers, a class added later starts with its list, and
 * `php artisan subjects:backfill` brings schools already on the platform in
 * line. The offerings are marked is_default and a School Admin cannot
 * remove them; they can still add subjects of their own.
 *
 * A class's defaults are passed around as an ordered array of
 * name => ['category' => SubjectCategory, 'aliases' => list<string>]; an
 * empty array means the class has none.
 */
class DefaultSubjects
{
    /**
     * The groups in config, in order.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function groups(): array
    {
        return (array) config('subjects.class_defaults', []);
    }

    /**
     * One group's defaults, the stream's extra subjects after the general
     * ones.
     *
     * @return array<string, array{category: SubjectCategory, aliases: list<string>}>
     */
    public static function definitionsFor(string $group, ?string $stream = null): array
    {
        $settings = self::groups()[$group] ?? null;

        if ($settings === null) {
            return [];
        }

        $subjects = (array) ($settings['subjects'] ?? []);

        if ($stream !== null) {
            $subjects += (array) ($settings['streams'][$stream] ?? []);
        }

        $definitions = [];

        foreach ($subjects as $name => [$category, $aliases]) {
            $definitions[(string) $name] = [
                'category' => SubjectCategory::tryFrom((string) $category) ?? SubjectCategory::General,
                'aliases' => array_values(array_map('strval', (array) $aliases)),
            ];
        }

        return $definitions;
    }

    /**
     * The defaults for a school's class (empty for none, or no class).
     *
     * @return array<string, array{category: SubjectCategory, aliases: list<string>}>
     */
    public static function forClass(?SchoolClass $class): array
    {
        if ($class === null) {
            return [];
        }

        $class->loadMissing('academicLevel');

        $group = self::groupFor(
            (string) $class->name,
            $class->academicLevel?->name,
            fn () => self::positionInLevel($class),
        );

        return $group === null ? [] : self::definitionsFor($group, self::streamOf($class, $group));
    }

    /**
     * The defaults for a class known only by name, e.g. an examination's
     * class. The school's class of that name is used when there is one.
     *
     * @return array<string, array{category: SubjectCategory, aliases: list<string>}>
     */
    public static function forClassName(int $schoolId, string $className): array
    {
        $class = SchoolClass::query()
            ->where('school_id', $schoolId)
            ->where('name', $className)
            ->with('academicLevel')
            ->first();

        if ($class !== null) {
            return self::forClass($class);
        }

        $group = self::groupFor($className, null, fn () => null);

        return $group === null ? [] : self::definitionsFor($group, self::streamFromName($className, $group));
    }

    /**
     * Which group a class belongs to: by its own name first, then, only when
     * the name says nothing ("Year 7"), by the level it is filed under and
     * its place in that level.
     *
     * @param  callable(): ?int  $position  the class's place in its level, from 1
     */
    public static function groupFor(string $className, ?string $levelName, callable $position): ?string
    {
        $groups = self::groups();

        foreach ($groups as $key => $settings) {
            if (preg_match((string) $settings['classes'], $className) === 1) {
                return (string) $key;
            }
        }

        if ($levelName === null) {
            return null;
        }

        $place = null;

        foreach ($groups as $key => $settings) {
            $level = $settings['level'] ?? null;

            if ($level === null || preg_match((string) $level, $levelName) !== 1) {
                continue;
            }

            // "Nursery 3" under the Nursery level names its own place, and no
            // group is for it: it is not guessed from its position.
            if (preg_match((string) $level, $className) === 1) {
                return null;
            }

            $positions = $settings['positions'] ?? null;

            if ($positions === null) {
                return (string) $key;
            }

            $place ??= $position();

            if ($place !== null && in_array($place, (array) $positions, true)) {
                return (string) $key;
            }
        }

        return null;
    }

    /**
     * A subject name reduced to what matters when comparing two of them:
     * lower case, "&" read as "and", dots dropped, hyphens and runs of
     * spaces made one space. "Literature-in-English" and "literature in
     * english", "Food & Nutrition" and "Food and Nutrition" come out the same.
     */
    public static function normalize(?string $name): string
    {
        $name = mb_strtolower((string) $name);
        $name = str_replace('&', ' and ', $name);
        $name = str_replace('.', '', $name);
        $name = str_replace('-', ' ', $name);
        $name = preg_replace('/\s*\/\s*/u', '/', $name) ?? $name;
        $name = preg_replace('/\s+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    /**
     * Which of the given defaults a subject name means, by its own name or
     * any alias, or null when it is none of them.
     *
     * @param  array<string, array{category: SubjectCategory, aliases: list<string>}>  $defaults
     */
    public static function defaultNameIn(array $defaults, ?string $name): ?string
    {
        return self::lookup($defaults)[self::normalize($name)] ?? null;
    }

    /**
     * Which default subject, in any class's list, a name means, or null.
     */
    public static function defaultNameFor(?string $name): ?string
    {
        return self::defaultNameIn(self::allDefinitions(), $name);
    }

    /**
     * Every normalized spelling that means the same subject as $name: the
     * name itself, and when it is a default (or an alias of one), that
     * default's name and aliases.
     *
     * @return list<string>
     */
    public static function spellingsOf(string $name): array
    {
        $spellings = [self::normalize($name)];
        $default = self::defaultNameFor($name);

        if ($default !== null) {
            foreach (self::spellingsByDefault()[$default] ?? [] as $spelling) {
                $spellings[] = $spelling;
            }
        }

        return array_values(array_unique($spellings));
    }

    /**
     * The catalogue entry for one default subject, created when the
     * catalogue has none. The exact name is used first; an alias only when
     * it is not itself a default somewhere else ("Number Work" is Nursery's
     * own subject, never KG's Numeracy).
     *
     * @param  array{category: SubjectCategory, aliases: list<string>}  $definition
     * @param  Collection<int, Subject>  $catalogue  updated with anything created
     */
    public static function catalogueSubject(string $name, array $definition, Collection $catalogue): Subject
    {
        $exact = self::normalize($name);
        $match = $catalogue->first(fn (Subject $subject) => self::normalize($subject->name) === $exact);

        if ($match === null) {
            $defaultNames = self::spellingsByDefault();

            foreach ($definition['aliases'] as $alias) {
                $alias = self::normalize($alias);

                if (self::isDefaultName($alias, $defaultNames)) {
                    continue;
                }

                $match = $catalogue->first(fn (Subject $subject) => self::normalize($subject->name) === $alias);

                if ($match !== null) {
                    break;
                }
            }
        }

        if ($match === null) {
            $match = Subject::create(['name' => $name, 'category' => $definition['category']]);
            $catalogue->push($match);
        }

        return $match;
    }

    /**
     * Makes sure every default subject of every list is in the catalogue.
     */
    public static function catalogueAll(?Collection $catalogue = null): Collection
    {
        $catalogue ??= Subject::query()->orderBy('id')->get();

        foreach (self::allDefinitions() as $name => $definition) {
            self::catalogueSubject($name, $definition, $catalogue);
        }

        return $catalogue;
    }

    /**
     * Gives every class in the school its default subjects. Safe to run any
     * number of times. Returns how many subjects were added.
     */
    public static function ensureFor(School $school, ?Collection $catalogue = null): int
    {
        $classes = $school->schoolClasses()->with('academicLevel')->get();

        if ($classes->isEmpty()) {
            return 0;
        }

        $catalogue ??= Subject::query()->orderBy('id')->get();

        return $classes->sum(fn (SchoolClass $class) => self::ensureForClass($class, $catalogue));
    }

    /**
     * Same as ensureFor(), for one class:
     *
     *  - adds each default subject the class does not offer yet, unless it
     *    already offers it under another spelling (case, spaces and the
     *    aliases in config all count as the same subject);
     *  - marks the class's default offerings is_default;
     *  - a subject marked default that is no longer on the class's list is
     *    taken off the class when no score has been recorded for it there,
     *    and otherwise kept as the school's own subject.
     *
     * Nothing a school added itself is removed.
     */
    public static function ensureForClass(SchoolClass $class, ?Collection $catalogue = null): int
    {
        $defaults = self::forClass($class);

        $offerings = SubjectOffering::with('subject')
            ->where('school_id', $class->school_id)
            ->where('class_name', $class->name)
            ->get();

        if ($defaults === [] && ! $offerings->contains('is_default', true)) {
            return 0;
        }

        $catalogue ??= Subject::query()->orderBy('id')->get();
        $added = 0;
        $claimed = collect();

        foreach ($defaults as $name => $definition) {
            $existing = $offerings->first(fn (SubjectOffering $offering) => ! $claimed->contains($offering->id)
                && $offering->subject
                && self::defaultNameIn($defaults, $offering->subject->name) === $name);

            if ($existing) {
                $claimed->push($existing->id);

                if (! $existing->is_default) {
                    $existing->forceFill(['is_default' => true])->save();
                }

                continue;
            }

            $subject = self::catalogueSubject($name, $definition, $catalogue);

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

        $stale = $offerings->filter(fn (SubjectOffering $offering) => $offering->is_default && ! $claimed->contains($offering->id));

        if ($stale->isNotEmpty()) {
            $scored = self::scoredSubjectNames((int) $class->school_id, (string) $class->name);

            foreach ($stale as $offering) {
                // Any spelling of the subject counts as a score for it.
                if ($offering->subject && collect(self::spellingsOf($offering->subject->name))->intersect($scored)->isNotEmpty()) {
                    $offering->forceFill(['is_default' => false])->save();
                } else {
                    $offering->delete();
                }
            }
        }

        return $added;
    }

    /**
     * Every school on the platform, as `subjects:backfill` runs it.
     *
     * @return array{schools: int, added: int}
     */
    public static function backfillAll(): array
    {
        $catalogue = self::catalogueAll();
        $schools = 0;
        $added = 0;

        School::query()->chunkById(100, function ($chunk) use (&$schools, &$added, $catalogue) {
            foreach ($chunk as $school) {
                $schools++;
                $added += self::ensureFor($school, $catalogue);
            }
        });

        return ['schools' => $schools, 'added' => $added];
    }

    /**
     * Puts a class's subject names in result-sheet order: its defaults
     * first, in the order in config, then any other subjects A to Z.
     *
     * @param  Collection<int, string>  $names
     * @param  array<string, array{category: SubjectCategory, aliases: list<string>}>  $defaults
     * @return Collection<int, string>
     */
    public static function order(Collection $names, array $defaults): Collection
    {
        return self::sortByName($names, $defaults, fn ($name) => (string) $name);
    }

    /**
     * The same order for a list of anything that has a subject name, e.g. an
     * examination's subjects on a report card.
     *
     * @template TItem
     *
     * @param  iterable<TItem>  $items
     * @param  array<string, array{category: SubjectCategory, aliases: list<string>}>  $defaults
     * @param  callable(TItem): ?string  $name
     * @return Collection<int, TItem>
     */
    public static function sortByName(iterable $items, array $defaults, callable $name): Collection
    {
        $lookup = self::lookup($defaults);
        $position = array_flip(array_keys($defaults));
        $last = count($position);

        $key = function ($item) use ($name, $lookup, $position, $last): array {
            $label = (string) $name($item);
            $default = $lookup[self::normalize($label)] ?? null;

            return [$default !== null ? $position[$default] : $last, self::normalize($label)];
        };

        return collect($items)
            ->sort(fn ($a, $b) => $key($a) <=> $key($b))
            ->values();
    }

    /**
     * Every default subject in config, each name once, with the aliases
     * from every list that names it.
     *
     * @return array<string, array{category: SubjectCategory, aliases: list<string>}>
     */
    private static function allDefinitions(): array
    {
        $all = [];

        foreach (self::groups() as $key => $settings) {
            $streams = [null, ...array_keys((array) ($settings['streams'] ?? []))];

            foreach ($streams as $stream) {
                foreach (self::definitionsFor((string) $key, $stream) as $name => $definition) {
                    if (! isset($all[$name])) {
                        $all[$name] = $definition;

                        continue;
                    }

                    $all[$name]['aliases'] = array_values(array_unique([...$all[$name]['aliases'], ...$definition['aliases']]));
                }
            }
        }

        return $all;
    }

    /**
     * Default name => its normalized name and aliases.
     *
     * @return array<string, list<string>>
     */
    private static function spellingsByDefault(): array
    {
        return collect(self::allDefinitions())
            ->map(fn (array $definition, string $name) => array_values(array_unique(array_map(
                fn ($spelling) => self::normalize($spelling),
                [$name, ...$definition['aliases']],
            ))))
            ->all();
    }

    /**
     * @param  array<string, list<string>>  $spellingsByDefault
     */
    private static function isDefaultName(string $normalized, array $spellingsByDefault): bool
    {
        foreach (array_keys($spellingsByDefault) as $name) {
            if (self::normalize($name) === $normalized) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalized name or alias => default name. A default's own name wins
     * over another default's alias.
     *
     * @param  array<string, array{category: SubjectCategory, aliases: list<string>}>  $defaults
     * @return array<string, string>
     */
    private static function lookup(array $defaults): array
    {
        $lookup = [];

        foreach (array_keys($defaults) as $name) {
            $lookup[self::normalize($name)] = $name;
        }

        foreach ($defaults as $name => $definition) {
            foreach ($definition['aliases'] as $alias) {
                $lookup[self::normalize($alias)] ??= $name;
            }
        }

        return $lookup;
    }

    /**
     * The class's stream for a group that has streams: the one set on the
     * class, or failing that the word in its name.
     */
    private static function streamOf(SchoolClass $class, string $group): ?string
    {
        if (empty(self::groups()[$group]['streams'])) {
            return null;
        }

        if ($class->stream instanceof ClassStream) {
            return $class->stream->value;
        }

        return self::streamFromName((string) $class->name, $group);
    }

    private static function streamFromName(string $className, string $group): ?string
    {
        if (empty(self::groups()[$group]['streams'])) {
            return null;
        }

        return match (true) {
            preg_match('/\bscience\b/i', $className) === 1 => ClassStream::Science->value,
            preg_match('/\barts?\b/i', $className) === 1 => ClassStream::Art->value,
            preg_match('/\bcommercial\b/i', $className) === 1 => ClassStream::Commercial->value,
            default => null,
        };
    }

    /**
     * The class's place in its level, from 1, in the level's own order.
     */
    private static function positionInLevel(SchoolClass $class): ?int
    {
        if ($class->academic_level_id === null) {
            return null;
        }

        $ids = SchoolClass::query()
            ->where('academic_level_id', $class->academic_level_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('id');

        $index = $ids->search($class->id);

        return $index === false ? null : $index + 1;
    }

    /**
     * The normalized names of the subjects that have at least one recorded
     * score in any examination of this class.
     *
     * @return Collection<int, string>
     */
    private static function scoredSubjectNames(int $schoolId, string $className): Collection
    {
        return ExaminationSubject::query()
            ->whereHas('scores')
            ->whereHas('examination', fn ($query) => $query->where('school_id', $schoolId)->where('class_name', $className))
            ->distinct()
            ->pluck('name')
            ->map(fn ($name) => self::normalize($name))
            ->unique()
            ->values();
    }
}
