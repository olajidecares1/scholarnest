<?php

use App\Models\School;
use App\Support\ReportCardSample;

/**
 * A report card is read by parents, often printed, often photocopied. Grey on
 * white survives none of that.
 *
 * The rule: light background means near-black or the school's navy; dark
 * background means white; and no low-contrast grey anywhere information
 * actually lives. These tests measure it rather than eyeball it, a colour
 * that "looks fine" on a designer's screen is exactly how 4.44:1 shipped
 * across every form in the application unnoticed.
 */
beforeEach(function () {
    $this->school = School::factory()->create(['name' => 'Contrast School']);
});

/**
 * WCAG relative luminance.
 */
function relativeLuminance(string $hex): float
{
    $hex = ltrim($hex, '#');

    if (strlen($hex) === 3) {
        $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    }

    $channels = array_map(function (string $pair): float {
        $value = hexdec($pair) / 255;

        return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }, [substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2)]);

    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function contrastRatio(string $foreground, string $background): float
{
    $a = relativeLuminance($foreground);
    $b = relativeLuminance($background);

    return round((max($a, $b) + 0.05) / (min($a, $b) + 0.05), 2);
}

test('every colour the printed card sets for text clears the readable floor', function () {
    // The printed card is inline styles only, so every colour it uses can be
    // read straight out of the rendered HTML, no stylesheet to consult.
    $html = view('school-admin.results.pdf.report-card', ReportCardSample::for($this->school))->render();

    // The lookbehind matters: without it this also matches the "color:" inside
    // "background-color:", and every fill on the card gets judged as if it
    // were text sitting on the page.
    preg_match_all('/(?<![-\w])color:\s*(#[0-9a-fA-F]{6})/', $html, $matches);

    $colours = array_unique($matches[1]);

    expect($colours)->not->toBeEmpty();

    // What is drawn on the school's navy bars rather than on the page.
    $onNavy = ['#ffffff', '#f9fafb', '#c8a34a'];

    foreach ($colours as $colour) {
        $background = in_array(strtolower($colour), $onNavy, true)
            ? '#111a35'   // The navy header and footer bars.
            : '#ffffff';  // The page.

        expect(contrastRatio($colour, $background))
            ->toBeGreaterThanOrEqual(4.5, "{$colour} on {$background} is too faint to read");
    }
});

test('the screen card uses no washed-out grey utility', function () {
    $html = view('school-admin.results._report-card', ReportCardSample::for($this->school))->render();

    // gray-400 is 2.8:1 on white and gray-500 is 4.8:1 at best, neither
    // belongs on a document somebody photocopies.
    expect($html)->not->toContain('text-gray-400')
        ->and($html)->not->toContain('text-gray-500');
});

test('the result details panel uses no washed-out grey utility', function () {
    $html = view('school-admin.results._details', [
        ...ReportCardSample::for($this->school),
        'canEditTeacherRemark' => true,
        'canEditPrincipalRemark' => false,
    ])->render();

    expect($html)->not->toContain('text-gray-400')
        ->and($html)->not->toContain('text-gray-500');
});

test('the form hint colour clears the floor on the backgrounds it sits on', function () {
    // This one shipped failing: #64789f measured 4.44:1 on white, just under.
    $css = file_get_contents(resource_path('css/app.css'));

    preg_match('/--field-hint:\s*(#[0-9a-fA-F]{6});/', $css, $match);

    expect($match[1] ?? null)->not->toBeNull();

    foreach (['#ffffff', '#f9fafb', '#f5f8fd'] as $background) {
        expect(contrastRatio($match[1], $background))->toBeGreaterThanOrEqual(4.5);
    }
});

test('both cards name each signature block for what it is', function () {
    // "Class Teacher's Signature", not "Class Teacher", each block says what
    // it holds rather than who it belongs to.
    foreach (['school-admin.results._report-card', 'school-admin.results.pdf.report-card'] as $template) {
        $html = view($template, ReportCardSample::for($this->school))->render();

        expect($html)->toContain('Class Teacher&#039;s Signature')
            ->and($html)->toContain('Principal&#039;s Signature')
            ->and($html)->toContain('Class Teacher&#039;s Remark');
    }
});
