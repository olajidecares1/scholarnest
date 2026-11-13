<?php

namespace App\Support;

use Illuminate\View\ComponentAttributeBag;

/**
 * Keeps x-model on the platform's form field components working when it is
 * given something that cannot be written to.
 *
 * x-model is two-way: Alpine reads the expression to fill the field AND
 * assigns to it as the person types, by compiling "<expression> = value".
 * Edit forms across the platform were written as
 *
 *     x-model="editing ? editing.first_name : ''"
 *
 * which reads perfectly well and cannot be assigned to. Alpine refuses to
 * compile the assignment ("Invalid left-hand side in assignment"), the field
 * never receives the record's value, and the Edit dialog for a staff member,
 * a pupil, a book, an event or a news post opens with empty fields. Seventy
 * fields on eighteen pages were written this way.
 *
 * What those forms mean is one-way: fill the field from the record being
 * edited, or the default for a new one, and submit whatever is typed. So such
 * an expression is FOLLOWED instead (followExpression() below): whenever what
 * it reads changes (Edit pressed on another row, Add pressed), the field takes
 * the new value. Not on the first render, though, where the field may already
 * hold what the person typed before a validation error sent the form back;
 * that is only filled in when the field is empty. A plain path ("form.name",
 * "rows[0].email") is assignable and is left exactly as written.
 */
final class AlpineModel
{
    private const ASSIGNABLE = '/^\s*[A-Za-z_$][\w$]*(?:\s*\.\s*[A-Za-z_$][\w$]*|\s*\[[^\[\]]+\])*\s*$/';

    public static function isAssignable(string $expression): bool
    {
        return preg_match(self::ASSIGNABLE, $expression) === 1;
    }

    /**
     * An attribute bag with any unassignable x-model (and its modifiers)
     * turned into x-bind:value.
     */
    public static function oneWayWhereNeeded(ComponentAttributeBag $attributes): ComponentAttributeBag
    {
        foreach ($attributes->getAttributes() as $key => $expression) {
            if (! str_starts_with((string) $key, 'x-model') || ! is_string($expression) || self::isAssignable($expression)) {
                continue;
            }

            $attributes = $attributes->except($key)->merge(['x-effect' => self::followExpression($expression, '$el.value')]);
        }

        return $attributes;
    }

    /**
     * Alpine code that keeps $target in step with $expression one way.
     *
     * The first run (the page rendering) only fills an empty field; every
     * later run, which happens only when something the expression reads
     * changes, sets it.
     */
    public static function followExpression(string $expression, string $target): string
    {
        return "const __v = (({$expression}) ?? ''); "
            ."if (\$el._anFollowed) { {$target} = String(__v) } "
            ."else { \$el._anFollowed = true; if ({$target} === '' || {$target} === null) { {$target} = String(__v) } }";
    }
}
