<?php

namespace App\Services\Import;

/**
 * Finds the school's own class for a class name written in an uploaded list.
 *
 * Exact first, ignoring case, spacing and punctuation ("jss1" is "JSS 1"),
 * then loosely, the way classes are often written: "Pry 1", "P1",
 * "Primary One" and "Primary I" are all "Primary 1"; "JS1", "J.S.S 1" and
 * "Junior Secondary 1" are "JSS 1"; "SS 2" and "Senior Secondary 2" are
 * "SSS 2". A loose spelling is only used when exactly one of the school's
 * classes reads that way, so it can never pick between two.
 *
 * Shared by the bulk staff upload and the bulk student upload, so the two
 * understand a class name the same way.
 */
class ClassNameMatcher
{
    /** @var array<string, string> exact key => the school's spelling */
    private array $classes = [];

    /** @var array<string, string> loose key => the school's spelling */
    private array $looseClasses = [];

    /**
     * @param  list<string>  $classNames  the school's classes, as they are spelt
     */
    public function __construct(array $classNames)
    {
        $loose = [];

        foreach ($classNames as $name) {
            $this->classes[self::key($name)] ??= $name;
            $loose[self::looseKey($name)][$name] = true;
        }

        foreach ($loose as $key => $names) {
            if (count($names) === 1) {
                $this->looseClasses[$key] = array_key_first($names);
            }
        }
    }

    /**
     * The school's class, as the school spells it, or null.
     */
    public function match(?string $name): ?string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return null;
        }

        return $this->classes[self::key($name)] ?? $this->looseClasses[self::looseKey($name)] ?? null;
    }

    /**
     * Why a class name was not recognised, with the closest of the school's
     * classes when it is only a letter or two away.
     */
    public function unknownMessage(string $name): string
    {
        if ($this->classes === []) {
            return "Class \"{$name}\" cannot be used because your school has no classes yet. Create them in Academics first.";
        }

        $key = self::key($name);
        $closest = null;
        $distance = PHP_INT_MAX;

        foreach ($this->classes as $candidate => $spelling) {
            $d = levenshtein($key, (string) $candidate);

            if ($d < $distance) {
                [$closest, $distance] = [$spelling, $d];
            }
        }

        $hint = $closest !== null && $distance <= 2 ? " Did you mean {$closest}?" : '';

        return "Class \"{$name}\" is not one of your school's classes.{$hint}";
    }

    public static function key(string $name): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', mb_strtolower($name));
    }

    /**
     * How a class is often written, reduced to one form. See the class
     * description for examples.
     */
    public static function looseKey(string $name): string
    {
        $text = mb_strtolower($name);
        $text = (string) preg_replace('/(?<=[a-z])\.(?=[a-z])/', '', $text);
        $text = (string) preg_replace('/junior\s+secondary(\s+school)?/', 'jss', $text);
        $text = (string) preg_replace('/senior\s+secondary(\s+school)?/', 'sss', $text);
        $text = (string) preg_replace('/([a-z])(\d)/', '$1 $2', $text);
        $text = (string) preg_replace('/(\d)([a-z])/', '$1 $2', $text);

        $tokens = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $words = [
            'pry' => 'primary', 'pri' => 'primary', 'prim' => 'primary', 'p' => 'primary',
            'js' => 'jss', 'jhs' => 'jss', 'jsss' => 'jss',
            'ss' => 'sss', 'shs' => 'sss', 's' => 'sss',
            'kindergarten' => 'kg', 'kindergarden' => 'kg', 'k' => 'kg',
            'nur' => 'nursery', 'n' => 'nursery',
            'yr' => 'year', 'gr' => 'grade', 'bas' => 'basic',
        ];
        $numbers = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10, 'eleven' => 11, 'twelve' => 12];
        $roman = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6];
        $levels = ['primary', 'jss', 'sss', 'kg', 'nursery', 'year', 'grade', 'basic', 'class', 'form', 'reception'];

        $out = [];

        foreach ($tokens as $i => $token) {
            $previous = $out[$i - 1] ?? null;

            // A lone letter ("P", "S") only stands for a level before a number.
            if (isset($words[$token]) && (strlen($token) > 1 || ctype_digit($tokens[$i + 1] ?? ''))) {
                $token = $words[$token];
            } elseif (isset($numbers[$token])) {
                $token = (string) $numbers[$token];
            } elseif (isset($roman[$token]) && in_array($previous, $levels, true)) {
                $token = (string) $roman[$token];
            }

            $out[$i] = $token;
        }

        return implode('', $out);
    }
}
