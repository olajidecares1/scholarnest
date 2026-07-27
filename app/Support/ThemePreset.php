<?php

namespace App\Support;

class ThemePreset
{
    /**
     * @var array<string, array{label: string, colors: array<string, string>}>
     */
    public const PRESETS = [
        'facebook-blue' => [
            'label' => 'Facebook Blue',
            'colors' => [
                '50' => '#e7f1fe', '100' => '#cfe3fd', '200' => '#9cc5fb', '300' => '#6aa8f9', '400' => '#438bf6',
                '500' => '#1877f2', '600' => '#166fe5', '700' => '#1259bd', '800' => '#0e4593', '900' => '#0a336d',
            ],
        ],
        'emerald-green' => [
            'label' => 'Emerald Green',
            'colors' => [
                '50' => '#ecfdf5', '100' => '#d1fae5', '200' => '#a7f3d0', '300' => '#6ee7b7', '400' => '#34d399',
                '500' => '#10b981', '600' => '#059669', '700' => '#047857', '800' => '#065f46', '900' => '#064e3b',
            ],
        ],
        'royal-purple' => [
            'label' => 'Royal Purple',
            'colors' => [
                '50' => '#f5f3ff', '100' => '#ede9fe', '200' => '#ddd6fe', '300' => '#c4b5fd', '400' => '#a78bfa',
                '500' => '#8b5cf6', '600' => '#7c3aed', '700' => '#6d28d9', '800' => '#5b21b6', '900' => '#4c1d95',
            ],
        ],
        'sunset-orange' => [
            'label' => 'Sunset Orange',
            'colors' => [
                '50' => '#fff7ed', '100' => '#ffedd5', '200' => '#fed7aa', '300' => '#fdba74', '400' => '#fb923c',
                '500' => '#f97316', '600' => '#ea580c', '700' => '#c2410c', '800' => '#9a3412', '900' => '#7c2d12',
            ],
        ],
        'crimson-red' => [
            'label' => 'Crimson Red',
            'colors' => [
                '50' => '#fef2f2', '100' => '#fee2e2', '200' => '#fecaca', '300' => '#fca5a5', '400' => '#f87171',
                '500' => '#ef4444', '600' => '#dc2626', '700' => '#b91c1c', '800' => '#991b1b', '900' => '#7f1d1d',
            ],
        ],
    ];

    public static function colors(string $preset): array
    {
        return (self::PRESETS[$preset] ?? self::PRESETS['facebook-blue'])['colors'];
    }

    public static function label(string $preset): string
    {
        return (self::PRESETS[$preset] ?? self::PRESETS['facebook-blue'])['label'];
    }

    /**
     * @return string CSS custom property declarations for the given preset.
     */
    public static function cssVariables(string $preset): string
    {
        $declarations = collect(self::colors($preset))
            ->map(fn (string $hex, string $shade) => "--color-primary-{$shade}: {$hex};")
            ->implode(' ');

        return ":root { {$declarations} }";
    }
}
