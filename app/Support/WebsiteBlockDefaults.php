<?php

namespace App\Support;

use App\Models\SchoolWebsite;

/**
 * Generates the default set of `WebsiteBlock` specs for each public page,
 * seeded from a school's existing flat `SchoolWebsite` fields, so a school
 * with zero saved blocks still renders exactly what it has today. Mirrors
 * the null-fallback pattern used by `SchoolWebsite::heroLayout()` before it,
 * generalized across every page.
 *
 * Every method returns a flat list of block specs (plain arrays, not
 * persisted) shaped like the `website_blocks` table columns plus `section`.
 */
class WebsiteBlockDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function defaultStyle(string $type): array
    {
        return match ($type) {
            'button' => [
                'font_family' => 'Inter', 'font_weight' => '700', 'font_size' => 14,
                'line_height' => 1, 'letter_spacing' => 0, 'text_transform' => 'none', 'align' => 'center',
                'bg_color' => '#166fe5', 'text_color' => '#ffffff',
                'hover_bg_color' => '#1259bd', 'hover_text_color' => '#ffffff',
                'border_width' => 0, 'border_color' => '#000000',
                'radius' => 8, 'shadow' => 'lg',
                'padding_x' => 24, 'padding_y' => 12,
                'visible' => true,
            ],
            'card' => [
                'bg_color' => '#ffffff', 'bg_opacity' => 100,
                'blur' => 'none',
                'border_color' => '#e5e7eb', 'border_opacity' => 100, 'border_width' => 1,
                'radius' => 10, 'shadow' => 'sm',
                'font_family' => 'Inter', 'font_weight' => '700', 'font_size' => 16,
                'line_height' => 1.3, 'letter_spacing' => 0, 'text_transform' => 'none', 'align' => 'left',
                'color' => '#111827',
                'visible' => true,
            ],
            default => [
                'font_family' => 'Inter', 'font_weight' => '400', 'font_size' => 16,
                'line_height' => 1.5, 'letter_spacing' => 0, 'text_transform' => 'none', 'align' => 'left',
                'color' => '#111827',
                'visible' => true,
            ],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forHome(SchoolWebsite $website): array
    {
        $blocks = [
            self::text('hero', 'title', $website->hero_title, [
                'x' => 6, 'y' => 32, 'w' => 54, 'h' => 16,
                'style' => ['font_size' => 48, 'font_weight' => '800', 'color' => '#ffffff'],
            ]),
        ];

        if ($website->hero_subtitle) {
            $blocks[] = self::text('hero', 'subtitle', $website->hero_subtitle, [
                'x' => 6, 'y' => 50, 'w' => 42, 'h' => 10,
                'style' => ['font_size' => 18, 'color' => '#f3f4f6'],
            ]);
        }

        $blocks[] = self::button('hero', $website->cta_text ?: 'Apply for Admission', $website->cta_url ?: '#contact', [
            'x' => 6, 'y' => 66, 'w' => 20, 'h' => 7,
        ]);

        if ($website->hero_secondary_text) {
            $blocks[] = self::button('hero', $website->hero_secondary_text, $website->hero_secondary_url ?: '#campus-life', [
                'x' => 28, 'y' => 66, 'w' => 22, 'h' => 7,
                'style' => ['bg_color' => 'transparent', 'border_width' => 1, 'border_color' => '#ffffff', 'radius' => 999],
            ]);
        }

        foreach (self::defaultFeatures() as $index => $feature) {
            $blocks[] = self::card('feature-icons', "feature-{$index}", $feature['label'], $feature['blurb'], [
                'x' => 1 + ($index % 6) * 16.5, 'y' => 5, 'w' => 15, 'h' => 90,
                'style' => ['align' => 'center', 'shadow' => 'none', 'border_width' => 0, 'bg_color' => 'transparent'],
            ]);
        }

        $stats = $website->stats ?: [];
        foreach ($stats as $index => $stat) {
            $blocks[] = self::text('stats', "stat-{$index}", ($stat['label'] ?? '').': '.($stat['value'] ?? ''), [
                'x' => 4 + ($index % 4) * 24, 'y' => 10, 'w' => 22, 'h' => 12,
                'style' => ['font_size' => 20, 'font_weight' => '700', 'align' => 'center', 'color' => '#ffffff'],
            ]);
        }

        if ($website->slogan) {
            $blocks[] = self::text('slogan', 'slogan', $website->slogan, [
                'x' => 10, 'y' => 20, 'w' => 80, 'h' => 20,
                'style' => ['font_size' => 32, 'font_weight' => '800', 'align' => 'center', 'color' => '#ffffff'],
            ]);
        }

        if ($website->principal_message) {
            $blocks[] = self::text('principal', 'message', $website->principal_message, [
                'x' => 10, 'y' => 10, 'w' => 80, 'h' => 40,
            ]);
        }

        if ($website->quote_text) {
            $blocks[] = self::text('quote', 'quote', $website->quote_text, [
                'x' => 10, 'y' => 15, 'w' => 80, 'h' => 30,
                'style' => ['font_size' => 22, 'align' => 'center'],
            ]);
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forAbout(SchoolWebsite $website): array
    {
        $blocks = [];

        if ($website->hero_subtitle) {
            $blocks[] = self::text('intro', 'intro', $website->hero_subtitle, [
                'x' => 10, 'y' => 10, 'w' => 80, 'h' => 20, 'style' => ['align' => 'center', 'color' => '#ffffff'],
            ]);
        }

        $blocks[] = self::text('body', 'body', $website->about_text ?: '', [
            'x' => 10, 'y' => 10, 'w' => 80, 'h' => 60, 'style' => ['align' => 'center'],
        ]);

        if ($website->slogan) {
            $blocks[] = self::text('slogan', 'slogan', $website->slogan, [
                'x' => 10, 'y' => 20, 'w' => 80, 'h' => 20,
                'style' => ['font_size' => 28, 'font_weight' => '800', 'align' => 'center', 'color' => '#ffffff'],
            ]);
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forAdmissions(SchoolWebsite $website): array
    {
        $blocks = [];

        if ($website->admissions_intro) {
            $blocks[] = self::text('intro', 'intro', $website->admissions_intro, [
                'x' => 10, 'y' => 10, 'w' => 80, 'h' => 30, 'style' => ['align' => 'center', 'color' => '#ffffff'],
            ]);
        }

        $steps = $website->admissions_steps ?: [];
        foreach ($steps as $index => $step) {
            $blocks[] = self::card('steps', "step-{$index}", $step['title'] ?? '', $step['description'] ?? null, [
                'x' => 2 + ($index % 4) * 24.5, 'y' => 5, 'w' => 22, 'h' => 40,
            ]);
        }

        $requirements = $website->admissions_requirements ?: [];
        foreach ($requirements as $index => $requirement) {
            $blocks[] = self::text('requirements', "requirement-{$index}", $requirement, [
                'x' => 10, 'y' => 5 + $index * 12, 'w' => 80, 'h' => 10,
            ]);
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forContact(SchoolWebsite $website): array
    {
        $blocks = [];
        $index = 0;

        foreach ([
            'phone' => $website->contact_phone,
            'email' => $website->contact_email,
            'address' => $website->contact_address,
        ] as $key => $value) {
            if (! $value) {
                $index++;

                continue;
            }

            $blocks[] = self::card('cards', $key, ucfirst($key), $value, [
                'x' => 2 + $index * 33, 'y' => 5, 'w' => 30, 'h' => 40,
                'style' => ['align' => 'center'],
            ]);
            $index++;
        }

        return $blocks;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function forFooter(SchoolWebsite $website): array
    {
        $blocks = [
            self::text('description', 'description', $website->footer_text ?: ($website->slogan_tagline ?: 'Nurturing minds, building character, and shaping future leaders through excellence in education.'), [
                'x' => 4, 'y' => 10, 'w' => 90, 'h' => 40, 'style' => ['color' => '#d1d5db', 'font_size' => 13],
            ]),
        ];

        $contactIndex = 0;

        foreach ([
            'address' => $website->contact_address,
            'phone' => $website->contact_phone,
            'email' => $website->contact_email,
        ] as $value) {
            if (! $value) {
                $contactIndex++;

                continue;
            }

            $blocks[] = self::text('contact', "contact-{$contactIndex}", $value, [
                'x' => 4, 'y' => 10 + $contactIndex * 25, 'w' => 90, 'h' => 20,
                'style' => ['color' => '#d1d5db', 'font_size' => 13],
            ]);
            $contactIndex++;
        }

        $blocks[] = self::text('cta', 'heading', 'Ready to Begin Your Journey?', [
            'x' => 10, 'y' => 10, 'w' => 80, 'h' => 30,
            'style' => ['font_size' => 28, 'font_weight' => '800', 'align' => 'center', 'color' => '#ffffff'],
        ]);
        $blocks[] = self::button('cta', $website->cta_text ?: 'Apply for Admission', $website->cta_url ?: '#contact', [
            'x' => 38, 'y' => 55, 'w' => 24, 'h' => 12, 'style' => ['align' => 'center'],
        ]);

        return $blocks;
    }

    /**
     * @return list<array{label: string, blurb: string}>
     */
    private static function defaultFeatures(): array
    {
        return [
            ['label' => 'Qualified Teachers', 'blurb' => 'Passionate educators dedicated to excellence'],
            ['label' => 'Modern Facilities', 'blurb' => 'Smart classrooms & world-class amenities'],
            ['label' => 'Holistic Education', 'blurb' => 'Academic, social & character development'],
            ['label' => 'Global Perspective', 'blurb' => 'Preparing future leaders for the world'],
            ['label' => 'Safe Environment', 'blurb' => 'Secure, caring & inclusive community'],
            ['label' => 'Proven Excellence', 'blurb' => 'Consistent outstanding academic results'],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function text(string $section, string $key, ?string $content, array $overrides = []): array
    {
        return self::block($section, $key, 'text', $content, null, null, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function button(string $section, ?string $content, ?string $url, array $overrides = []): array
    {
        return self::block($section, 'button', 'button', $content, null, $url, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function card(string $section, string $key, ?string $content, ?string $secondaryContent, array $overrides = []): array
    {
        return self::block($section, $key, 'card', $content, $secondaryContent, null, $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private static function block(string $section, string $key, string $type, ?string $content, ?string $secondaryContent, ?string $url, array $overrides = []): array
    {
        $style = array_merge(self::defaultStyle($type), $overrides['style'] ?? []);

        return [
            'key' => $key,
            'section' => $section,
            'type' => $type,
            'content' => $content,
            'secondary_content' => $secondaryContent,
            'url' => $url,
            'x' => $overrides['x'] ?? 10,
            'y' => $overrides['y'] ?? 10,
            'w' => $overrides['w'] ?? 30,
            'h' => $overrides['h'] ?? 15,
            'style' => $style,
            'sort_order' => 0,
        ];
    }
}
