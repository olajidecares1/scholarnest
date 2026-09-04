<?php

namespace App\Support;

use App\Models\IdCardTemplate;
use App\Models\School;

/**
 * The card every school starts from.
 *
 * The reference design is not one school's card - it is the DEFAULT, which
 * each school then edits into its own. That makes where the defaults live a
 * real decision rather than a detail: they were written out five times over,
 * once in each card face and once more in the template editor, so a school
 * that never touched the editor and a school that opened it and saved without
 * changing anything could end up looking at two different cards.
 *
 * They are defined once here instead. A template's own colours win when it has
 * them; these are what fills the gap, and what the editor pre-fills a new
 * template with, so both roads lead to the same card.
 */
final class IdCardDesign
{
    /**
     * The lighter of the two school colours. Used for a staff badge and the
     * landscape card's rule work.
     */
    public const PRIMARY = '#1d4ed8';

    /**
     * The navy that carries the masthead, the footer bar and the back panel.
     */
    public const SECONDARY = '#111a35';

    /**
     * The red of the stripe under the masthead, the tagline, the holder's
     * badge and the contact icons on the back.
     */
    public const ACCENT = '#c8102e';

    /**
     * @return array{primary: string, secondary: string, accent: string}
     */
    public static function colors(?IdCardTemplate $template): array
    {
        return [
            'primary' => $template?->primary_color ?: self::PRIMARY,
            'secondary' => $template?->secondary_color ?: self::SECONDARY,
            'accent' => $template?->accent_color ?: self::ACCENT,
        ];
    }

    /**
     * What the back of the card says when a school has not written its own.
     *
     * Phrased as the reference card phrases it, with the school's own name
     * where the reference has its own - a default a school can accept as it
     * stands, not a placeholder it has to replace.
     */
    public static function instructions(School $school): string
    {
        return implode("\n", [
            "This card is the property of {$school->name}.",
            'It must be worn at all times on campus.',
            'It is non-transferable and must not be tampered with.',
            'Report loss or damage to the school office immediately.',
        ]);
    }

    /**
     * The values a brand-new template opens with in the editor.
     *
     * @return array<string, string>
     */
    public static function newTemplateDefaults(School $school): array
    {
        return [
            'primary_color' => self::PRIMARY,
            'secondary_color' => self::SECONDARY,
            'accent_color' => self::ACCENT,
            'instructions' => self::instructions($school),
        ];
    }
}
