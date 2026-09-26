<?php

namespace App\Support;

/**
 * The Font Awesome icon a form field shows, whatever the caller passed.
 *
 * Release 1.2 draws every field icon with Font Awesome, the same icon set as
 * the sidebars and the rest of the portals. Most forms were written when
 * fields took a hand-drawn SVG path (icon="M4.5 19.5c..."), and there are a
 * few hundred of those call sites. Rather than rewrite each one, and have the
 * next form written from an old example bring an SVG back, the field
 * components translate here: a Font Awesome class passes straight through, a
 * known path becomes its Font Awesome equivalent, and anything else gets a
 * neutral pen rather than a blank space.
 */
class FieldIcon
{
    public const FALLBACK = 'fa-pen';

    /**
     * The paths the portals' forms use, next to the icon each one drew.
     *
     * @var array<string, string>
     */
    private const BY_PATH = [
        'M15.5 8.5a3.5 3.5 0 11-7 0 3.5 3.5 0 017 0zM13 11l-6.5 6.5' => 'fa-key',
        'M4 6.5h16a1 1 0 011 1V17a1 1 0 01-1 1H4a1 1 0 01-1-1V7.5a1 1 0 011-1z' => 'fa-id-card',
        'M3 6.5a2 2 0 012-2h14a2 2 0 012 2v11a2 2 0 01-2 2H5a2 2 0 01-2-2v-11z M3 7l9 6.5L21 7' => 'fa-envelope',
        'M6.5 3.5h11a1 1 0 011 1v15a1 1 0 01-1 1h-11a1 1 0 01-1-1v-15a1 1 0 011-1z' => 'fa-mobile-screen-button',
        'M4 21h16M6 21V8l6-4 6 4v13M10 21v-4h4v4' => 'fa-school',
        'M3 7l9 6 9-6M4 5h16a1 1 0 011 1v12a1 1 0 01-1 1H4a1 1 0 01-1-1V6a1 1 0 011-1z' => 'fa-envelope',
        'M6.5 3h3l1.5 4-2 1.5a12 12 0 005.5 5.5L16 12l4 1.5v3a2 2 0 01-2.2 2A16.5 16.5 0 014.5 5.2 2 2 0 016.5 3z' => 'fa-phone',
        'M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4M17 9.3a2.7 2.7 0 012.2 4.7' => 'fa-user',
        'M6.5 4.5h2l1.2 4-1.8 1.5a11 11 0 005.1 5.1l1.5-1.8 4 1.2v2a1.5 1.5 0 01-1.6 1.5A15 15 0 015 6.1a1.5 1.5 0 011.5-1.6z' => 'fa-phone',
        'M4 21h16 M5 21V10M19 21V10 M3 10l9-6 9 6 M8 10v11M12 10v11M16 10v11' => 'fa-building-columns',
        'M4 21h16M5 21V10M19 21V10M3 10l9-6 9 6M8 10v11M12 10v11M16 10v11' => 'fa-building-columns',
        'M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z' => 'fa-message',
        'M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z' => 'fa-clipboard-check',
        'M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z' => 'fa-graduation-cap',
        'M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z' => 'fa-calendar',
        'M8 12.3l2.6 2.6L16.3 9' => 'fa-circle-check',
        'M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5' => 'fa-chalkboard',
        'M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z' => 'fa-star',
        'M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z' => 'fa-file-lines',
        'M12 3a9 9 0 100 18 9 9 0 000-18z' => 'fa-globe',
        'M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z' => 'fa-location-dot',
        'M4 20V10.5L12 4l8 6.5V20 M9 20v-6h6v6' => 'fa-house',
        'M5 4.5h3l1.5 4-2 1.5a11 11 0 005 5l1.5-2 4 1.5v3a1 1 0 01-1 1A15 15 0 015 5.5a1 1 0 011-1z' => 'fa-phone',
        'M6.5 3.5h3l1.5 4-2 1.5a12 12 0 006 6l1.5-2 4 1.5v3a1 1 0 01-1 1A15.5 15.5 0 015.5 4.5a1 1 0 011-1z' => 'fa-phone',
        'M12 12a4 4 0 100-8 4 4 0 000 8zm0 2c-4.4 0-8 2.7-8 6h16c0-3.3-3.6-6-8-6z' => 'fa-user-tie',
        'M7 8h10M7 12h6M5 4h14a1 1 0 011 1v11a1 1 0 01-1 1H9l-4 3.5V5a1 1 0 011-1z' => 'fa-quote-left',
        'M12 3.5l2.6 5.3 5.9.9-4.2 4.1 1 5.8-5.3-2.8-5.3 2.8 1-5.8-4.2-4.1 5.9-.9z' => 'fa-star',
        'M4 10.5L12 4l8 6.5V19a1 1 0 01-1 1h-4v-6H9v6H5a1 1 0 01-1-1v-8.5z' => 'fa-house',
        'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zM8 11V7a4 4 0 118 0v4' => 'fa-lock',
        'M5 10.5a2 2 0 012-2h10a2 2 0 012 2v7a2 2 0 01-2 2H7a2 2 0 01-2-2v-7z M8 10.5V7a4 4 0 018 0v3.5' => 'fa-lock',
        'M4 19.5A2.5 2.5 0 016.5 17H20 M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z' => 'fa-book',
        'M4 20V10.5L12 4l8 6.5V20' => 'fa-door-open',
        'M4 13.5V8.5a1 1 0 011-1h1.5l1.5-3h8l1.5 3H19a1 1 0 011 1v5' => 'fa-bus',
        'M4 6h16M4 12h10M4 18h7' => 'fa-heading',
        'M3.5 6.2S5.5 5 8.5 5s5 1.2 5 1.2v12S11.5 17 8.5 17s-5 1.2-5 1.2v-12z' => 'fa-book-open',
        'M9.5 9a2.5 2.5 0 115 .5c0 1.5-2.5 2-2.5 3.5M12 17h.01' => 'fa-circle-question',
        'M4.5 19.5c.6-3 3-5 6-5s5.4 2 6 5M9.5 5.8a2.7 2.7 0 115.4 3.4' => 'fa-user',
        'M6.5 3.5h3l1.5 4-2 1.5a11 11 0 005 5l1.5-2 4 1.5v3a1.5 1.5 0 01-1.6 1.5A16.5 16.5 0 015 5.1a1.5 1.5 0 011.5-1.6z' => 'fa-phone',
        'M12 21s7-6.5 7-11.5A7 7 0 105 9.5C5 14.5 12 21 12 21z M12 11.5a2 2 0 100-4 2 2 0 000 4z' => 'fa-location-dot',
        'M4 20V10M10 20V4M16 20v-7M20 20v-3' => 'fa-chart-simple',
        'M12 21a9 9 0 100-18 9 9 0 000 18z' => 'fa-clock',
        'M12 3l8 3.6v2L12 12 4 8.6v-2L12 3z' => 'fa-layer-group',
        'M7 8h10M7 12h10M7 16h6' => 'fa-hashtag',
        'M7 13.5l2.2 2.2L14 11' => 'fa-circle-check',
        'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06A1.65 1.65 0 004.6 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06A1.65 1.65 0 009 4.6a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z' => 'fa-gear',
        'M4.5 8.5a2 2 0 012-2h11a2 2 0 012 2v7a2 2 0 01-2 2h-11a2 2 0 01-2-2v-7z M4.5 9.5l7.1 4.6a1 1 0 001.1 0l6.8-4.6' => 'fa-envelope-open-text',
    ];

    /**
     * The Font Awesome class for a field icon, or null when there is none.
     */
    public static function fa(?string $icon): ?string
    {
        $icon = trim((string) $icon);

        if ($icon === '') {
            return null;
        }

        if (str_starts_with($icon, 'fa-')) {
            return $icon;
        }

        return self::BY_PATH[$icon] ?? self::BY_PATH[preg_replace('/\s+/', ' ', $icon)] ?? self::FALLBACK;
    }

    /**
     * The icon a field of this input type should show when the form did not
     * choose one. Only the types whose meaning is unmistakable; a plain text
     * field is left without an icon rather than given a guess.
     */
    public static function forType(?string $type): ?string
    {
        return match ($type) {
            'email' => 'fa-envelope',
            'tel' => 'fa-phone',
            'password' => 'fa-lock',
            'date', 'datetime-local' => 'fa-calendar',
            'time' => 'fa-clock',
            'url' => 'fa-link',
            'search' => 'fa-magnifying-glass',
            default => null,
        };
    }

    /**
     * Whether a path has its own entry, rather than falling back.
     */
    public static function knows(string $icon): bool
    {
        return str_starts_with($icon, 'fa-') || isset(self::BY_PATH[trim($icon)]);
    }
}
