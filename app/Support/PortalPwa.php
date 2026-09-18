<?php

namespace App\Support;

use App\Enums\PortalApp;
use App\Models\School;
use App\Models\Setting;

/**
 * A school's portal, described as an installable application.
 *
 * The web app manifest is what turns a page into something with an icon on a
 * home screen, and every field in it that identifies the app is school-scoped:
 * the name, the icons, the colour, and above all the start URL. That last one
 * is the whole point of the feature, once installed, tapping the icon opens
 * THIS school's portal and no other, without anybody having to remember an
 * address or keep a bookmark.
 *
 * Paths are RELATIVE throughout, never absolute. A manifest is resolved
 * against its own URL, so the same document works unchanged whether a school
 * is reached on the platform host, on its free subdomain or on its own domain,
 * and an install made from one of those keeps the person on the host they
 * installed from.
 */
final class PortalPwa
{
    /**
     * The neutral fallback, used when neither the school nor the platform has
     * uploaded anything. Deliberately not white: an icon that vanishes into a
     * light home screen looks like a failed install.
     */
    private const FALLBACK_THEME = '#4f46e5';

    /**
     * Memoised for this instance, which is one page render. Not static: a
     * static would outlive the request on a long-running worker and go on
     * serving the fingerprint of a logo that has since been replaced.
     */
    private ?string $fingerprint = null;

    public function __construct(
        public readonly School $school,
        public readonly PortalApp $portal,
    ) {}

    /**
     * The manifest document.
     *
     * @return array<string, mixed>
     */
    public function manifest(): array
    {
        return [
            'id' => $this->portal->manifestId($this->school),
            'name' => "{$this->school->name} {$this->portal->label()}",
            'short_name' => $this->shortName(),
            'description' => "{$this->portal->label()} for {$this->school->name}.",
            'start_url' => $this->portal->startUrl($this->school),
            'scope' => $this->portal->scope($this->school),
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'theme_color' => $this->themeColor(),
            'background_color' => '#ffffff',
            'lang' => 'en',
            'dir' => 'ltr',
            'categories' => ['education'],
            'icons' => $this->icons(),
        ];
    }

    /**
     * What a launcher shows under the icon.
     *
     * The school first, because a phone with two schools' apps on it is the
     * case that has to work, a parent with children at two schools sees two
     * icons, and "Parent" twice would tell them nothing. Truncation is the
     * launcher's business; giving it the distinguishing word first is ours.
     */
    public function shortName(): string
    {
        $school = trim((string) $this->school->name);
        $short = "{$school} {$this->portal->shortName()}";

        return mb_strlen($short) <= 30 ? $short : mb_substr($short, 0, 30);
    }

    /**
     * The school's own colour where it has one, the platform's otherwise.
     *
     * This paints the status bar and the splash screen, so it is the first
     * thing seen on launch and the last thing that should say somebody else's
     * brand.
     */
    public function themeColor(): string
    {
        $colour = $this->school->website?->brand_primary_color;

        return $this->isHexColour($colour) ? $colour : self::FALLBACK_THEME;
    }

    /**
     * Both sizes Android requires, each declared twice.
     *
     * "any" is the icon as uploaded. "maskable" is the same image, which the
     * launcher may crop to whatever shape it likes, a circle, a squircle,
     * and the icon route pads the artwork into the safe zone so that cropping
     * cannot take a bite out of a school's crest.
     *
     * @return list<array<string, string>>
     */
    public function icons(): array
    {
        $icons = [];

        foreach ([192, 512] as $size) {
            $src = $this->iconUrl($size);

            $icons[] = ['src' => $src, 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'any'];
            $icons[] = ['src' => $src, 'sizes' => "{$size}x{$size}", 'type' => 'image/png', 'purpose' => 'maskable'];
        }

        return $icons;
    }

    /**
     * The AkademicNest icon, no school in it.
     *
     * The app on the home screen is AkademicNest's, so the icon is the
     * platform's mark for every school alike. What distinguishes one school's
     * app from another is the NAME beneath it, which is where a school's
     * identity belongs on a launcher.
     *
     * Fingerprinted because a launcher caches the icon it was handed at
     * install time and has no reason to ask for the same URL again; a new
     * fingerprint is the only way a re-uploaded mark ever reaches a phone
     * that installed the app last term. See iconFingerprint().
     */
    public function iconUrl(int $size): string
    {
        return route('pwa.icon', [
            'size' => $size,
            'v' => $this->iconFingerprint(),
        ], absolute: false);
    }

    public function manifestUrl(): string
    {
        return route('pwa.manifest', [
            'school' => $this->school,
            'portal' => $this->portal->value,
        ], absolute: false);
    }

    /**
     * Derived from what the icon is actually drawn from: the AkademicNest logo
     * the Super Admin has uploaded, or the bundled artwork when there is none.
     *
     * THIS IS WHAT MAKES A NEW LOGO REACH AN ALREADY-INSTALLED APP. A launcher
     * caches the icon it was handed at install time and never asks for that
     * URL again, so replacing the bytes behind a fixed address changes nothing
     * on a phone that installed the app last term. What it does do is re-read
     * the manifest, which is served with a five-minute cache; a different logo
     * gives a different fingerprint, which gives a different icon URL in that
     * manifest, which the browser fetches and puts in place of the old icon.
     *
     * The stored path changes on every upload, see ThemeController, so the
     * path alone is enough and no file has to be read to compute this.
     *
     * Memoised per instance, which is one page render: this is asked once per
     * icon in the manifest plus once for apple-touch-icon, and that should not
     * be six settings queries for a value that cannot change mid-request.
     */
    public function iconFingerprint(): string
    {
        return $this->fingerprint ??= substr(hash('sha256', $this->iconSource()), 0, 10);
    }

    /**
     * A string that changes when, and only when, the icon's artwork does.
     */
    private function iconSource(): string
    {
        $uploaded = Setting::platformLogoPath();

        if ($uploaded) {
            return 'logo:'.$uploaded;
        }

        $path = public_path('images/pwa-512x512.png');

        return is_file($path)
            ? 'bundled:'.filesize($path).'-'.filemtime($path)
            : 'no-artwork';
    }

    private function isHexColour(?string $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }
}
