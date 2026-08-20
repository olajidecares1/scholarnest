<?php

namespace App\Support;

/**
 * Generates a Tailwind-style 9-step (50-900) color shade scale from a single
 * arbitrary hex color, anchoring the input at shade 600 (the dominant shade
 * used across this app's `bg-primary-600`/`hover:bg-primary-700` usage).
 *
 * Deliberately separate from {@see ThemePreset}, which serves a different,
 * unrelated concern (5 fixed platform-wide presets for the internal admin UI).
 *
 * Mirrored in `resources/js/color-scale.js` for instant client-side preview —
 * keep the ratio tables below identical in both files.
 */
final class BrandColorScale
{
    /**
     * Fraction of the distance from l600 toward 100 (white) for each lighter shade.
     *
     * @var array<string, float>
     */
    private const LIGHTEN_RATIOS = [
        '50' => 0.95,
        '100' => 0.85,
        '200' => 0.65,
        '300' => 0.46,
        '400' => 0.24,
        '500' => 0.10,
    ];

    /**
     * Multiplier applied directly to l600 for each darker shade.
     *
     * @var array<string, float>
     */
    private const DARKEN_MULTIPLIERS = [
        '700' => 0.82,
        '800' => 0.62,
        '900' => 0.44,
    ];

    /**
     * Multiplier applied to s600 for every shade (including 600 itself).
     *
     * @var array<string, float>
     */
    private const SATURATION_MULTIPLIERS = [
        '50' => 0.75, '100' => 0.82, '200' => 0.88, '300' => 0.92, '400' => 0.96,
        '500' => 0.98, '600' => 1.0, '700' => 1.02, '800' => 1.04, '900' => 1.06,
    ];

    /**
     * @return array<string, string> shade (e.g. "600") => "#rrggbb"
     */
    public static function fromHex(string $hex): array
    {
        [$h, $s, $l] = self::hexToHsl($hex);

        $scale = ['600' => '#'.strtolower(ltrim($hex, '#'))];

        foreach (self::LIGHTEN_RATIOS as $shade => $ratio) {
            $shadeL = $l + ($ratio * (100 - $l));
            $shadeS = min(100, $s * self::SATURATION_MULTIPLIERS[$shade]);
            $scale[$shade] = self::hslToHex([$h, $shadeS, $shadeL]);
        }

        foreach (self::DARKEN_MULTIPLIERS as $shade => $multiplier) {
            $shadeL = $l * $multiplier;
            $shadeS = min(100, $s * self::SATURATION_MULTIPLIERS[$shade]);
            $scale[$shade] = self::hslToHex([$h, $shadeS, $shadeL]);
        }

        ksort($scale, SORT_NUMERIC);

        return $scale;
    }

    /**
     * @return string CSS custom property declarations (no `:root` wrapper — caller wraps it).
     */
    public static function cssVariables(string $prefix, string $hex): string
    {
        return collect(self::fromHex($hex))
            ->map(fn (string $value, string $shade) => "--color-{$prefix}-{$shade}: {$value};")
            ->implode(' ');
    }

    /**
     * @return array{0: float, 1: float, 2: float} [hue 0-360, saturation 0-100, lightness 0-100]
     */
    private static function hexToHsl(string $hex): array
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2)) / 255;
        $g = hexdec(substr($hex, 2, 2)) / 255;
        $b = hexdec(substr($hex, 4, 2)) / 255;

        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
        $l = ($max + $min) / 2;

        if ($max === $min) {
            return [0.0, 0.0, $l * 100];
        }

        $d = $max - $min;
        $s = $l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min);

        $h = match ($max) {
            $r => fmod(($g - $b) / $d, 6),
            $g => ($b - $r) / $d + 2,
            default => ($r - $g) / $d + 4,
        };

        $h *= 60;

        if ($h < 0) {
            $h += 360;
        }

        return [$h, $s * 100, $l * 100];
    }

    /**
     * @param  array{0: float, 1: float, 2: float}  $hsl
     */
    private static function hslToHex(array $hsl): string
    {
        [$h, $s, $l] = $hsl;
        $s = max(0, min(100, $s)) / 100;
        $l = max(0, min(100, $l)) / 100;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
        $m = $l - $c / 2;

        [$r, $g, $b] = match (true) {
            $h < 60 => [$c, $x, 0],
            $h < 120 => [$x, $c, 0],
            $h < 180 => [0, $c, $x],
            $h < 240 => [0, $x, $c],
            $h < 300 => [$x, 0, $c],
            default => [$c, 0, $x],
        };

        $r = (int) round(($r + $m) * 255);
        $g = (int) round(($g + $m) * 255);
        $b = (int) round(($b + $m) * 255);

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
