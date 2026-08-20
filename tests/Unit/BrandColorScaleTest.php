<?php

use App\Support\BrandColorScale;

test('a known hex round-trips exactly at shade 600', function () {
    $scale = BrandColorScale::fromHex('#1877f2');

    expect($scale['600'])->toBe('#1877f2');
});

test('every shade is a valid 6-digit hex color', function () {
    $scale = BrandColorScale::fromHex('#dc2626');

    expect($scale)->toHaveKeys(['50', '100', '200', '300', '400', '500', '600', '700', '800', '900']);

    foreach ($scale as $hex) {
        expect($hex)->toMatch('/^#[0-9a-f]{6}$/');
    }
});

test('lightness strictly decreases from shade 50 to shade 900', function () {
    $scale = BrandColorScale::fromHex('#7c3aed');
    $shades = ['50', '100', '200', '300', '400', '500', '600', '700', '800', '900'];

    $luminance = function (string $hex) {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        return 0.299 * $r + 0.587 * $g + 0.114 * $b;
    };

    for ($i = 1; $i < count($shades); $i++) {
        expect($luminance($scale[$shades[$i - 1]]))->toBeGreaterThan($luminance($scale[$shades[$i]]));
    }
});

test('css variable declarations use the given prefix and hex values', function () {
    $css = BrandColorScale::cssVariables('secondary', '#059669');

    expect($css)
        ->toContain('--color-secondary-600: #059669;')
        ->toContain('--color-secondary-50:')
        ->toContain('--color-secondary-900:');
});
