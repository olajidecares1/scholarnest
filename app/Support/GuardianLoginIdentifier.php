<?php

namespace App\Support;

use App\Models\Guardian;
use App\Models\School;
use Illuminate\Support\Collection;

/**
 * Which parent is trying to sign in.
 *
 * Parents sign in with their Parent ID or their phone number, never their
 * email address. An email is a thing a parent may not have, may share with
 * their spouse, or may change; the Parent ID is issued by the school and the
 * phone number is what the school already holds for them.
 *
 * Resolution is deliberately done here rather than by handing a column name to
 * Auth::attempt(), for two reasons:
 *
 * 1. A phone number is not one string. The school may hold "08031234567"
 *    while the parent types "+234 803 123 4567", and both are the same
 *    telephone. Comparing them needs normalising, which SQL does badly.
 *
 * 2. Phone numbers are not unique. Two parents of the same child - or a
 *    mistyped record - can share one, and "the first row that matches" is the
 *    wrong answer when the question is who somebody is. An ambiguous phone
 *    number is refused rather than guessed at.
 *
 * Every lookup is scoped to one school. Parent IDs and phone numbers are only
 * unique within a school, so an unscoped query could hand a parent at one
 * school the account of a parent at another.
 */
final class GuardianLoginIdentifier
{
    /**
     * The result of resolving what somebody typed.
     */
    public function __construct(
        public readonly ?Guardian $guardian = null,
        public readonly bool $ambiguous = false,
    ) {}

    /**
     * Find the one parent in this school matching what was typed.
     *
     * Returns an instance whose guardian is null when nothing matched, and
     * whose $ambiguous is true when a phone number belongs to more than one
     * parent - a case that must be refused, not resolved.
     */
    public static function resolve(School $school, string $input): self
    {
        $input = trim($input);

        if ($input === '') {
            return new self;
        }

        // An email address is not a parent login. Refused outright rather than
        // quietly failing to match, so the rule is enforced here and not only
        // by the absence of an email box on the form.
        if (str_contains($input, '@')) {
            return new self;
        }

        $byId = Guardian::query()
            ->where('school_id', $school->id)
            ->whereRaw('LOWER(guardian_number) = ?', [mb_strtolower($input)])
            ->first();

        if ($byId !== null) {
            return new self($byId);
        }

        return self::byPhone($school, $input);
    }

    /**
     * A phone number reduced to what actually identifies it.
     *
     * Everything that is punctuation or spacing goes, then the national
     * trunk "0" and the +234 country code, so that 08031234567,
     * +2348031234567 and 0803 123 4567 all reduce to the same string.
     *
     * Returns null for anything too short to be a telephone number, which
     * keeps a Parent ID that happens to be numeric from being read as one.
     */
    public static function normalisePhone(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '234')) {
            $digits = substr($digits, 3);
        }

        $digits = ltrim($digits, '0');

        return strlen($digits) >= 7 ? $digits : null;
    }

    private static function byPhone(School $school, string $input): self
    {
        $normalised = self::normalisePhone($input);

        if ($normalised === null) {
            return new self;
        }

        // Normalising happens in PHP, over this school's stored numbers.
        //
        // The obvious optimisation - a LIKE on the last digits to narrow the
        // set first - is wrong, and quietly so: it matches the RAW column, so
        // a school that records "0803 123 4567" with spaces would never match
        // "08031234567", which is exactly the difference this method exists to
        // see past. It cost nothing to write and would have locked those
        // parents out of phone login entirely.
        //
        // So two columns are read for one school's parents and compared
        // properly. That is a small query against a bounded set, run once per
        // sign-in attempt, and it is correct for every way a number can be
        // written down.
        /** @var Collection<int, string> $candidates */
        $candidates = Guardian::query()
            ->where('school_id', $school->id)
            ->whereNotNull('phone')
            ->where('phone', '<>', '')
            ->pluck('phone', 'id');

        $matchedIds = $candidates
            ->filter(fn (string $phone) => self::normalisePhone($phone) === $normalised)
            ->keys();

        if ($matchedIds->count() > 1) {
            return new self(ambiguous: true);
        }

        if ($matchedIds->isEmpty()) {
            return new self;
        }

        return new self(Guardian::find($matchedIds->first()));
    }
}
