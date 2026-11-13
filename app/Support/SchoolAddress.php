<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * A school's physical address, reduced to one comparable form.
 *
 * One school, one location, one account. A school is identified by WHERE it
 * is, not by its name (two schools may share a name in different towns) and
 * not by its email (a school that registered once can always find another
 * address to register with). The same street written two ways must therefore
 * be recognised as the same street:
 *
 *   "No. 12, Allen Ave., Ikeja, Lagos State"
 *   "12 allen avenue ikeja lagos"
 *
 * both normalise to "12 allen avenue ikeja lagos". The fingerprint, a hash of
 * that normal form, is what the database holds a unique index on, so two
 * registrations for one address can never both be saved, even at the same
 * moment.
 *
 * What is normalised: case, accents, punctuation, spacing, the common street
 * abbreviations (St, Rd, Ave, Cl, Cres, Dr, ...), house-number prefixes ("No.",
 * "#"), and words that add nothing to the location ("the", "state",
 * "Nigeria"). What is NOT: the words themselves. "12 Allen Avenue" and
 * "14 Allen Avenue" are different schools, and so are two schools on the same
 * street in different towns.
 */
final class SchoolAddress
{
    /** Shortest address that can identify a building: a number or name and a street. */
    public const MIN_LENGTH = 10;

    public const MIN_WORDS = 3;

    /** @var array<string, string> abbreviation => the word it stands for */
    private const WORDS = [
        'st' => 'street', 'str' => 'street', 'strt' => 'street',
        'rd' => 'road',
        'ave' => 'avenue', 'av' => 'avenue', 'avn' => 'avenue', 'avenu' => 'avenue',
        'cl' => 'close', 'cls' => 'close',
        'cres' => 'crescent', 'cresc' => 'crescent', 'crs' => 'crescent',
        'dr' => 'drive', 'drv' => 'drive',
        'ln' => 'lane',
        'blvd' => 'boulevard',
        'hwy' => 'highway', 'expy' => 'expressway', 'expwy' => 'expressway',
        'est' => 'estate',
        'ext' => 'extension',
        'jnct' => 'junction', 'jct' => 'junction', 'junc' => 'junction',
        'sq' => 'square',
        'pl' => 'place',
        'ct' => 'court',
        'mt' => 'mount',
        'opp' => 'opposite',
        'ph' => 'phase',
        'bstop' => 'busstop',
        'lga' => 'local government area',
        'fct' => 'federal capital territory',
    ];

    /** Words that never tell two locations apart. */
    private const NOISE = ['no', 'nos', 'num', 'number', 'the', 'state', 'nigeria'];

    /**
     * The comparable form, or null when there is nothing to compare.
     */
    public static function normalise(?string $address): ?string
    {
        $text = mb_strtolower(Str::ascii((string) $address));

        if (trim($text) === '') {
            return null;
        }

        $text = str_replace('&', ' and ', $text);
        $text = (string) preg_replace("/['’`]/u", '', $text);          // "John's" = "Johns"
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);     // punctuation, "#", spacing
        $text = (string) preg_replace('/\bbus\s+stop\b/', 'busstop', $text);

        $tokens = [];

        foreach (preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
            if (in_array($token, self::NOISE, true)) {
                continue;
            }

            // "1st" and "first" are left alone; "12b" and "12 b" are one house.
            $tokens[] = self::WORDS[$token] ?? $token;
        }

        $normal = (string) preg_replace('/(\d) ([a-z])\b/', '$1$2', implode(' ', $tokens));

        return $normal === '' ? null : $normal;
    }

    /**
     * What the unique index is on. Null for an empty address, so a school
     * that has not given one yet blocks nobody.
     */
    public static function fingerprint(?string $address): ?string
    {
        $normal = self::normalise($address);

        return $normal === null ? null : hash('sha256', $normal);
    }

    /**
     * Enough of an address to tell one building from another.
     */
    public static function isSpecific(?string $address): bool
    {
        $normal = self::normalise($address);

        return $normal !== null
            && mb_strlen(trim((string) $address)) >= self::MIN_LENGTH
            && count(explode(' ', $normal)) >= self::MIN_WORDS;
    }
}
