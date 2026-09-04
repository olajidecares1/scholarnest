<?php

namespace App\Support;

/**
 * A Font Awesome icon for a school facility.
 *
 * The facilities panel used to print the same fa-school over every entry, so a
 * library, a swimming pool and a sick bay all looked identical and the icons
 * carried no information at all. There is no icon column on the table and
 * asking every school to pick one for every facility would be worse, so the
 * icon is inferred from what the facility is called.
 *
 * Three things about the matching are deliberate.
 *
 * It matches WHOLE WORDS inside the name, not the whole name and not bare
 * substrings. Schools write "Main Library" and "The Junior Library Block", so
 * comparing the whole name is no good; but a substring search is worse - "art"
 * sits inside "Staff Quarters" and "bus" inside "Business", and both drew the
 * wrong icon with a straight face. Even anchoring to the start of a word is not
 * enough, because "bus" is how "Business" begins. A keyword ending in * is the
 * exception and matches anything built on that stem: "librar*" for libraries.
 *
 * ORDER decides ties, so the list runs specific to general. "Computer
 * Laboratory" holds both "computer" and "laborator" and would otherwise get a
 * flask.
 *
 * And the vaguest words - hall, block, office - are held back to a SECOND PASS.
 * A facility called "Block C" in the category "Laboratory" should draw a flask,
 * not a building, and it only can if the specific words get first refusal over
 * both the name and the category before "block" is allowed to answer.
 *
 * Unlike [IdCardFieldIcon] these are plain Font Awesome classes rather than SVG
 * data URIs. That class draws for dompdf as well as the browser and dompdf
 * cannot use an icon font; this one is only ever rendered on the website.
 */
final class FacilityIcon
{
    /**
     * Keyword to icon, most specific first.
     *
     * @var array<string, string>
     */
    private const RULES = [
        // Computing, ahead of the laboratories - a Computer Laboratory is a
        // computer room, not a science one.
        'computer' => 'fa-desktop',
        'ict' => 'fa-desktop',
        'technolog*' => 'fa-laptop-code',
        'internet' => 'fa-wifi',
        'network' => 'fa-wifi',

        // The sciences, each ahead of the generic laboratory below them.
        'chemistry' => 'fa-flask-vial',
        'physics' => 'fa-atom',
        'biology' => 'fa-dna',
        'microscope' => 'fa-microscope',
        'laborator*' => 'fa-flask',
        'science' => 'fa-flask',
        'lab' => 'fa-flask',

        // Sport.
        'swim*' => 'fa-person-swimming',
        'pool' => 'fa-person-swimming',
        'basketball' => 'fa-basketball',
        'tennis' => 'fa-table-tennis-paddle-ball',
        'gym*' => 'fa-dumbbell',
        'fitness' => 'fa-dumbbell',
        'football' => 'fa-futbol',
        'pitch' => 'fa-futbol',
        'stadium' => 'fa-futbol',
        'sport' => 'fa-futbol',
        'athletic' => 'fa-person-running',
        'track' => 'fa-person-running',

        // Learning.
        'librar*' => 'fa-book-open-reader',
        'reading' => 'fa-book-open-reader',
        'classroom' => 'fa-chalkboard-user',
        'class' => 'fa-chalkboard-user',
        'lecture' => 'fa-chalkboard-user',

        // The arts.
        'music' => 'fa-music',
        'band' => 'fa-music',
        'art' => 'fa-palette',
        'craft' => 'fa-palette',
        'studio' => 'fa-video',
        'media' => 'fa-video',
        'theat*' => 'fa-masks-theater',
        'drama' => 'fa-masks-theater',
        'auditorium' => 'fa-masks-theater',

        // Living.
        'dining' => 'fa-utensils',
        'cafeteria' => 'fa-utensils',
        'canteen' => 'fa-utensils',
        'kitchen' => 'fa-utensils',
        'refectory' => 'fa-utensils',
        'hostel' => 'fa-bed',
        'dormitor*' => 'fa-bed',
        'boarding' => 'fa-bed',
        'residen*' => 'fa-bed',

        // Care and safety.
        'clinic' => 'fa-kit-medical',
        'health' => 'fa-kit-medical',
        'medical' => 'fa-kit-medical',
        'sick' => 'fa-kit-medical',
        'infirmary' => 'fa-kit-medical',
        'counsel*' => 'fa-hand-holding-heart',
        'security' => 'fa-shield-halved',

        // Worship.
        'chapel' => 'fa-place-of-worship',
        'church' => 'fa-place-of-worship',
        'mosque' => 'fa-place-of-worship',
        'prayer' => 'fa-place-of-worship',
        'worship' => 'fa-place-of-worship',

        // Grounds and services.
        'playground' => 'fa-child-reaching',
        'play' => 'fa-child-reaching',
        'garden' => 'fa-seedling',
        'farm' => 'fa-seedling',
        'agric*' => 'fa-seedling',
        'tree' => 'fa-tree',
        'bus' => 'fa-bus',
        'transport' => 'fa-bus',
        'parking' => 'fa-square-parking',
        'park' => 'fa-square-parking',
        'water' => 'fa-droplet',
        'borehole' => 'fa-droplet',
        'power' => 'fa-bolt',
        'generator' => 'fa-bolt',
        'solar' => 'fa-bolt',
        'store*' => 'fa-boxes-stacked',
        'warehouse' => 'fa-boxes-stacked',
        'toilet' => 'fa-restroom',
        'restroom' => 'fa-restroom',
        'washroom' => 'fa-restroom',
    ];

    /**
     * Words too vague to beat anything, tried only once the list above has had
     * a look at both the name and the category.
     *
     * @var array<string, string>
     */
    private const GENERIC_RULES = [
        'hall' => 'fa-building-columns',
        'office' => 'fa-building',
        'admin*' => 'fa-building',
        'block' => 'fa-building',
        'complex' => 'fa-building',
        'centre' => 'fa-building',
        'center' => 'fa-building',
    ];

    /**
     * The default. A building that is none of the above is still a school
     * building, and a missing icon would leave a hole in the row.
     */
    private const FALLBACK = 'fa-school';

    /**
     * @param  string|null  $category  a second chance when the name says nothing
     */
    public static function for(?string $name, ?string $category = null): string
    {
        return self::match($name, self::RULES)
            ?? self::match($category, self::RULES)
            ?? self::match($name, self::GENERIC_RULES)
            ?? self::match($category, self::GENERIC_RULES)
            ?? self::FALLBACK;
    }

    /**
     * @param  array<string, string>  $rules
     */
    private static function match(?string $text, array $rules): ?string
    {
        if (blank($text)) {
            return null;
        }

        $haystack = mb_strtolower($text);

        foreach ($rules as $keyword => $icon) {
            if (preg_match(self::pattern($keyword), $haystack) === 1) {
                return $icon;
            }
        }

        return null;
    }

    /**
     * A keyword ending in * is a STEM and matches anything built on it:
     * "librar*" catches library and libraries. Anything else is a WHOLE WORD,
     * give or take a plural.
     *
     * The distinction is the whole game. Anchoring only to the start of a word
     * is not enough - "bus" starts "Business" and "lab" starts "Labour", so a
     * prefix rule drew a school bus for the Business Centre. Requiring the
     * whole word leaves both alone while still matching Bus, Buses and Labs.
     */
    private static function pattern(string $keyword): string
    {
        if (str_ends_with($keyword, '*')) {
            return '/\b'.preg_quote(rtrim($keyword, '*'), '/').'/';
        }

        return '/\b'.preg_quote($keyword, '/').'(?:s|es)?\b/';
    }

    /**
     * Every icon this class can return, for the test that checks they all
     * actually exist in the bundled Font Awesome. A typo here renders a blank
     * square on the website and nothing else would fail.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_values(array_unique([
            ...array_values(self::RULES),
            ...array_values(self::GENERIC_RULES),
            self::FALLBACK,
        ]));
    }
}
