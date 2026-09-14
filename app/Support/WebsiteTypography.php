<?php

namespace App\Support;

use App\Models\SchoolWebsite;

/**
 * The typeface and base weight a school's public website is set in.
 *
 * One place, because three others need the same answers and must not disagree:
 * the settings form draws the controls from it, the controller validates
 * against it, and the layout renders from it. A font list that lived in the
 * form and a validation rule that lived in the controller would drift the first
 * time either changed.
 *
 * SAFETY IS THE POINT of validating here rather than trusting the column. The
 * family is written into every visitor's stylesheet, so a value that reached
 * the page unchecked would be a way to point the site at an arbitrary font
 * host, or worse, to break out of the declaration. Nothing is emitted that is
 * not a key of the curated list in config/website_fonts.php, and a stored value
 * that has since been removed from that list falls back rather than rendering.
 */
final class WebsiteTypography
{
    public const DEFAULT_FAMILY = 'Inter';

    public const DEFAULT_WEIGHT = 400;

    /**
     * Every family a school may choose, each with the weights it really has.
     *
     * TWO SOURCES, one list. The Google families are fetched; the system ones
     * are only declared, and render where the visitor already has them. The
     * picker should not care which is which, a school choosing a font is
     * choosing a look, not a delivery mechanism, so they are merged here and
     * separated again only where the difference actually matters, which is the
     * two methods below.
     *
     * @return array<string, list<int>>
     */
    public static function families(): array
    {
        return [
            ...config('website_fonts.fonts', []),
            ...collect(config('website_fonts.system_fonts', []))
                ->map(fn (array $font) => $font['weights'])
                ->all(),
        ];
    }

    /**
     * The system families, which are never requested from anywhere.
     *
     * @return array<string, array{weights: list<int>, stack: string}>
     */
    public static function systemFamilies(): array
    {
        return config('website_fonts.system_fonts', []);
    }

    public static function isSystemFamily(string $family): bool
    {
        return array_key_exists($family, self::systemFamilies());
    }

    /**
     * The chosen family, or the default when nothing valid is stored.
     */
    public static function familyFor(?SchoolWebsite $website): string
    {
        $family = $website?->font_family;

        return $family && array_key_exists($family, self::families())
            ? $family
            : self::DEFAULT_FAMILY;
    }

    /**
     * The chosen base weight, clamped to what the chosen family publishes.
     *
     * A school can pick Montserrat at 800, then switch to PT Sans, which only
     * ships 400 and 700. Asking Google for a weight a family does not have
     * gets a stylesheet that omits it and a browser that synthesises the
     * difference, fake bold, visibly worse than the real thing. So the stored
     * number is snapped to the nearest weight the family really has.
     */
    public static function weightFor(?SchoolWebsite $website): int
    {
        $available = self::weightsFor(self::familyFor($website));
        $wanted = $website?->font_weight ?? self::DEFAULT_WEIGHT;

        if (in_array($wanted, $available, true)) {
            return $wanted;
        }

        return collect($available)
            ->sortBy(fn (int $weight) => abs($weight - $wanted))
            ->first() ?? self::DEFAULT_WEIGHT;
    }

    /**
     * @return list<int>
     */
    public static function weightsFor(string $family): array
    {
        return self::families()[$family] ?? [self::DEFAULT_WEIGHT];
    }

    /**
     * The font-family declaration for the page.
     *
     * The fallback stack is not decoration: a webfont that fails to load, a
     * blocked request, a flaky connection, would otherwise leave the page in
     * whatever the browser defaults to, which on some systems is a serif.
     */
    public static function stackFor(?SchoolWebsite $website): string
    {
        $family = self::familyFor($website);

        // A system family brings its OWN fallbacks, and they are chosen to
        // match: Georgia falls back to a serif, Courier New to a monospace,
        // Algerian to another heavy display face. The generic sans-serif stack
        // below would answer every one of them with the same wrong thing, a
        // school that picked a typewriter face and got Helvetica would
        // reasonably think the setting had failed.
        if (self::isSystemFamily($family)) {
            return self::systemFamilies()[$family]['stack'];
        }

        return "'".$family."', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif";
    }

    /**
     * What to ask Google for: the chosen family, and every weight the page
     * might use.
     *
     * The whole published range rather than just the base weight, because the
     * design sets headings in 600, 700 and 800 with utility classes. Loading
     * only the base would leave every one of those synthesised.
     *
     * @return array<string, list<int>>
     */
    public static function googleFontsFor(?SchoolWebsite $website): array
    {
        $family = self::familyFor($website);

        // Nothing to fetch for a system face. Asking Google for Algerian would
        // return a stylesheet for a family it has never heard of, and the page
        // would pay for the request and render in the fallback anyway.
        if (self::isSystemFamily($family)) {
            return [];
        }

        return [$family => self::weightsFor($family)];
    }
}
