<?php

namespace App\Support;

use App\Models\School;

/**
 * Where to find a school online, ready to render.
 *
 * Same arrangement as SchoolContact: the school's own settings are the source
 * of truth on every plan, and the website record - a Standard and Exclusive
 * feature - is consulted only where the school has left a field blank, so a
 * school that filled in its website years ago does not lose its links.
 *
 * Only the networks a school has actually filled in come back, so a footer or
 * a contact page can loop over the result rather than write out seven
 * conditionals and get one of them wrong.
 */
final class SchoolSocialLinks
{
    /**
     * Each network's label and Font Awesome mark.
     *
     * @var array<string, array{label: string, icon: string}>
     */
    private const NETWORKS = [
        'facebook_url' => ['label' => 'Facebook', 'icon' => 'fa-brands fa-facebook-f'],
        'instagram_url' => ['label' => 'Instagram', 'icon' => 'fa-brands fa-instagram'],
        'twitter_url' => ['label' => 'X', 'icon' => 'fa-brands fa-x-twitter'],
        'tiktok_url' => ['label' => 'TikTok', 'icon' => 'fa-brands fa-tiktok'],
        'youtube_url' => ['label' => 'YouTube', 'icon' => 'fa-brands fa-youtube'],
        'linkedin_url' => ['label' => 'LinkedIn', 'icon' => 'fa-brands fa-linkedin-in'],
    ];

    /**
     * The school's links, in a fixed order, omitting the ones it has not set.
     *
     * @return list<array{key: string, label: string, icon: string, url: string}>
     */
    public static function for(School $school): array
    {
        $site = $school->website;
        $links = [];

        foreach (self::NETWORKS as $column => $network) {
            $url = self::firstFilled($school->{$column}, $site?->{$column} ?? null);

            if ($url === null) {
                continue;
            }

            $links[] = [
                'key' => $column,
                'label' => $network['label'],
                'icon' => $network['icon'],
                'url' => self::normaliseUrl($url),
            ];
        }

        // WhatsApp last, and built rather than asked for: a school knows its
        // telephone number, not its wa.me link.
        $whatsapp = self::whatsappUrl($school->whatsapp_number);

        if ($whatsapp !== null) {
            $links[] = [
                'key' => 'whatsapp_number',
                'label' => 'WhatsApp',
                'icon' => 'fa-brands fa-whatsapp',
                'url' => $whatsapp,
            ];
        }

        return $links;
    }

    /**
     * A wa.me link from a telephone number, or null if there is not enough of
     * one to dial.
     *
     * wa.me wants digits only, with the country code and no leading zero, so
     * a Nigerian number written 0803… becomes 234803….
     */
    public static function whatsappUrl(?string $number): ?string
    {
        if (blank($number)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '0')) {
            $digits = '234'.ltrim($digits, '0');
        }

        return strlen($digits) >= 10 ? 'https://wa.me/'.$digits : null;
    }

    /**
     * A handle typed without a scheme still has to be a working link.
     *
     * Schools write "facebook.com/ourschool" as often as they paste a full
     * address, and an href without a scheme is read as a path on the current
     * site - which sends a visitor to a page of ours that does not exist.
     */
    private static function normaliseUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('#^https?://#i', $url) === 1 ? $url : 'https://'.ltrim($url, '/');
    }

    private static function firstFilled(?string ...$values): ?string
    {
        foreach ($values as $value) {
            if (filled($value)) {
                return $value;
            }
        }

        return null;
    }
}
