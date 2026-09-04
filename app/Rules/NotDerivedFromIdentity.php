<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * A password must not be made out of the account it protects.
 *
 * Password::defaults() already demands length, mixed case, a number and a
 * symbol - and "Greenfield2026!" satisfies every one of them while being the
 * first thing anybody would try against Greenfield College. Complexity rules
 * measure the shape of a password, not how guessable it is, and the most
 * guessable passwords on a platform like this are built from the two things
 * printed at the top of the school's own website.
 *
 * So this rule refuses a password that contains, or is contained by, any of
 * the identifiers the account is known by: the school's name, the email
 * address, the login id.
 *
 * IT COMPARES THE NORMALISED FORMS, not the literal strings. A rule that
 * only caught the exact word would be satisfied by "Gr33nf13ld!", which is no
 * harder to guess - so case, punctuation, spacing and the usual digit-for-
 * letter substitutions are all flattened before comparing.
 */
final class NotDerivedFromIdentity implements ValidationRule
{
    /**
     * The shortest run of characters worth matching on.
     *
     * Below this, ordinary words collide by accident: a school called "Ark"
     * would refuse every password containing "ark", "dark" and "marked"
     * included, and a rule that fires on innocent passwords teaches people to
     * work around it rather than to pick a better one.
     */
    private const MINIMUM_FRAGMENT = 4;

    /**
     * Digit-and-symbol substitutions, flattened before comparing.
     *
     * @var array<string, string>
     */
    private const SUBSTITUTIONS = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a',
        '5' => 's', '7' => 't', '8' => 'b', '9' => 'g',
        '@' => 'a', '$' => 's', '!' => 'i', '+' => 't',
    ];

    /**
     * @param  list<string|null>  $identifiers  The school's name, the email
     *                                          address, the login id - whatever
     *                                          this account is publicly known by.
     */
    public function __construct(private readonly array $identifiers) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $password = $this->normalise($value);

        if ($password === '') {
            return;
        }

        foreach ($this->fragments() as $fragment) {
            // Both directions. "greenfield" inside the password is the common
            // case; the password inside a longer identifier catches somebody
            // choosing a slice of their own email as a password.
            if (str_contains($password, $fragment) || str_contains($fragment, $password)) {
                $fail('The :attribute must not contain your school name, email address or login ID. Choose something unrelated to this account.');

                return;
            }
        }
    }

    /**
     * Every piece of the account's identity worth refusing, normalised.
     *
     * An email is broken up as well as taken whole: the part before the @ is
     * what people actually reuse, and the domain's own name is worth refusing
     * too - "greenfieldcollege" out of admin@greenfieldcollege.com is the same
     * guess as the school's name.
     *
     * @return list<string>
     */
    private function fragments(): array
    {
        $fragments = [];

        foreach (array_filter($this->identifiers) as $identifier) {
            $identifier = (string) $identifier;

            $parts = [$identifier];

            if (str_contains($identifier, '@')) {
                $parts[] = Str::before($identifier, '@');

                // The domain without its TLD: greenfieldcollege.com -> greenfieldcollege
                $domain = Str::after($identifier, '@');
                $parts[] = Str::beforeLast($domain, '.');
            }

            // Individual words too, so "Greenfield International College"
            // refuses "Greenfield!2026" and not merely the whole phrase. In
            // this context the generic half is guessable as well - every
            // customer here is a school, an academy or a college - so nothing
            // is exempted as too common.
            $parts = [...$parts, ...preg_split('/[^\p{L}\p{N}]+/u', $identifier, -1, PREG_SPLIT_NO_EMPTY)];

            foreach ($parts as $part) {
                $normalised = $this->normalise($part);

                if (mb_strlen($normalised) >= self::MINIMUM_FRAGMENT) {
                    $fragments[] = $normalised;
                }
            }
        }

        return array_values(array_unique($fragments));
    }

    /**
     * Lower-cased, stripped of everything that is not a letter or a digit, and
     * with the usual substitutions undone - so "Gr33n-F13ld" and "greenfield"
     * are the same string by the time they are compared.
     */
    private function normalise(string $value): string
    {
        $value = Str::of($value)->ascii()->lower()->toString();

        $value = strtr($value, self::SUBSTITUTIONS);

        return (string) preg_replace('/[^a-z0-9]/', '', $value);
    }
}
