<?php

namespace App\Support;

class GoogleFonts
{
    /**
     * The curated font-family => available-weights list from config/website_fonts.php,
     * exposed to the admin builder's font-family picker.
     *
     * @return array<string, list<int>>
     */
    public static function familyOptions(): array
    {
        return config('website_fonts.fonts', []);
    }

    /**
     * Scans a flat list of website blocks for every distinct font family +
     * weight actually referenced, so we only ever request fonts a school is
     * really using. Callers with blocks grouped by section should flatten
     * first, e.g. `$homeBlocks->flatten(1)`.
     *
     * @param  iterable<array<string, mixed>>  $blocks
     * @return array<string, list<int>>
     */
    public static function usedInBlocks(iterable $blocks): array
    {
        $used = [];

        foreach ($blocks as $block) {
            $style = $block['style'] ?? [];
            $family = $style['font_family'] ?? null;
            $weight = $style['font_weight'] ?? null;

            if (! $family || ! $weight) {
                continue;
            }

            $used[$family][] = (int) $weight;
        }

        return $used;
    }

    /**
     * Builds the `<link>` tags needed to load the given families/weights from
     * Google Fonts. Returns null when nothing beyond the default `Inter` (already
     * loaded locally via the `--font-sans` fallback stack, no network fetch needed)
     * is referenced.
     *
     * @param  array<string, list<int|string>>  $usedFamilies  family name => weights actually referenced
     */
    public static function linkTagFor(array $usedFamilies): ?string
    {
        $families = collect($usedFamilies)
            ->filter(fn (array $weights, string $family) => $family !== 'Inter' && filled($weights))
            ->map(fn (array $weights) => collect($weights)->map(fn ($weight) => (int) $weight)->unique()->sort()->values());

        if ($families->isEmpty()) {
            return null;
        }

        $query = $families
            ->map(fn ($weights, string $family) => str_replace(' ', '+', $family).':wght@'.$weights->implode(';'))
            ->values()
            ->implode('&family=');

        $href = "https://fonts.googleapis.com/css2?family={$query}&display=swap";

        return '<link rel="preconnect" href="https://fonts.googleapis.com">'
            .'<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
            ."<link rel=\"stylesheet\" href=\"{$href}\">";
    }
}
