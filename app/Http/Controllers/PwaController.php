<?php

namespace App\Http\Controllers;

use App\Enums\PlanKey;
use App\Enums\PortalApp;
use App\Models\School;
use App\Support\PortalPwa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * What a browser needs before it will offer to install a portal.
 *
 * Three documents: a manifest describing the app, an icon to put on the home
 * screen, and a service worker - the last of which is required for an install
 * prompt to appear at all, whether or not the app has any use for it offline.
 *
 * All three are public, and deliberately so. They are fetched by the browser
 * before anybody signs in, and a manifest that 401s is a manifest that never
 * produces an install prompt. Nothing here reveals anything the person
 * standing at the portal's front door cannot already see: the school's name,
 * its logo, its colour and the address they are already at.
 */
class PwaController extends Controller
{
    /**
     * How long a launcher may keep the icon. Long, because the URL carries a
     * fingerprint that changes whenever the artwork does.
     */
    private const ICON_MAX_AGE = 60 * 60 * 24 * 30;

    /**
     * 192 and 512 are what a web app manifest needs; 180 is what iOS reads
     * from apple-touch-icon.
     */
    public const ICON_SIZES = [180, 192, 512];

    /**
     * WHITE, and it has to be.
     *
     * The artwork is a blue rounded square with the mark PUNCHED OUT of it -
     * the mark is transparent, not white. Composite it onto the brand blue,
     * which looks like the obvious thing to do, and the mark fills with blue
     * and disappears: a plain blue tile with no logo on it. A test asserting
     * the icon matches the artwork is what caught that.
     *
     * On white it renders as drawn, and is fully opaque - which a maskable
     * icon must be, or a launcher cropping to a circle fills the corners with
     * whatever it likes, usually black.
     */
    private const ICON_BACKGROUND = '#FFFFFF';

    /**
     * The platform's app-icon artwork, largest first.
     *
     * Purpose-drawn as an app icon - the mark reversed out in white, inside
     * its own margin - rather than the page-header wordmark, which is
     * illegible at 192px.
     */
    private const ARTWORK = [
        'images/pwa-512x512.png',
        'images/pwa-192x192.png',
        'apple-touch-icon.png',
    ];

    /**
     * The manifest for one school's one portal.
     */
    public function manifest(School $school, string $portal): HttpResponse
    {
        $app = PortalApp::tryFrom($portal);

        abort_if($app === null, 404);
        abort_unless($school->hasActiveSubscription(), 404);

        // A Basic school has no student or parent accounts at all, so offering
        // to install either app would put an icon on a phone that opens onto
        // the "locked" page for ever. See EnsureSchoolHasPortalAccess, which
        // is the server-side rule this mirrors - it is the gate; this only
        // avoids advertising a door that one keeps shut.
        abort_unless($this->portalAvailable($school, $app), 404);

        return response()
            ->json((new PortalPwa($school, $app))->manifest(), 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            ->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=300');
    }

    /**
     * The home-screen icon: ALWAYS AkademicNest's, never a school's.
     *
     * The app being installed is AkademicNest, and the icon is what says so.
     * A school's own logo appears throughout its portal, on its website, on
     * its ID cards and in the browser tab - see App\Support\Favicon, which is
     * unchanged - but the thing that lands on a phone's home screen carries
     * the platform's mark.
     *
     * There is no school in this route at all, which is the point: one icon,
     * one URL, one cache entry for every school and every portal on the
     * platform. Two schools' apps are told apart by their NAME under the icon,
     * which is where the school's identity belongs.
     *
     * The artwork is the purpose-drawn set in public/images, at the exact
     * pixel sizes a launcher asks for. The Super Admin's uploaded platform
     * logo is deliberately NOT used: those uploads are wordmarks and lockups
     * sized for a page header, and one squeezed into a 192px tile comes out
     * illegible.
     */
    public function icon(Request $request, int $size): HttpResponse
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);

        $png = $this->drawIcon($size);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Length' => (string) strlen($png),
            // Only immutable when the caller passed the fingerprint, which
            // changes when the artwork does. A bare request has to stay fresh.
            'Cache-Control' => $request->query('v')
                ? 'public, max-age='.self::ICON_MAX_AGE.', immutable'
                : 'public, max-age=300',
        ]);
    }

    /**
     * The service worker.
     *
     * IT CACHES NO PAGE, EVER. Every portal here is multi-tenant and behind a
     * session: a cached dashboard is a dashboard that can be served to the
     * next person to open the browser, and on a shared family phone or a
     * school office machine that is a data leak with no attacker in it. Only
     * hashed build assets are cached, which are identical for every school and
     * carry nothing personal.
     *
     * Served from the root so one registration covers every portal on the
     * origin. The script is the same for all of them; the manifest is what
     * makes an installed app belong to a school.
     */
    public function serviceWorker(): HttpResponse
    {
        // Bumping this retires every cache the previous worker held. Tied to
        // the asset build, because that is the only thing this worker caches.
        $version = Vite::manifestHash() ?: 'dev';

        return response(view('pwa.service-worker', ['version' => $version])->render(), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            // A worker is only ever updated by the browser re-fetching this
            // script, so it must not be held in a cache.
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Service-Worker-Allowed' => '/',
        ]);
    }

    /**
     * The page an installed app shows when the device is offline.
     */
    public function offline(): HttpResponse
    {
        return response(view('pwa.offline')->render(), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }

    /**
     * Mirrors EnsureSchoolHasPortalAccess: staff and admin on every active
     * plan, families on Standard and Exclusive only.
     */
    private function portalAvailable(School $school, PortalApp $app): bool
    {
        return match ($app) {
            PortalApp::Student, PortalApp::Guardian => $school->hasPlanAccess(
                PlanKey::Standard,
                PlanKey::Exclusive,
            ),
            default => true,
        };
    }

    /**
     * Compose the icon: the AkademicNest mark, opaque, at the exact size.
     *
     * The bundled artwork is already drawn as an app icon, with its own margin
     * inside the tile - which is the safe zone a maskable icon needs, so it is
     * drawn at full size rather than inset a second time. Only the backing
     * colour is added, to make it opaque. See ICON_BACKGROUND for why that
     * colour is white and not the brand blue.
     */
    private function drawIcon(int $size): string
    {
        $canvas = imagecreatetruecolor($size, $size);

        [$r, $g, $b] = $this->rgb(self::ICON_BACKGROUND);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, $r, $g, $b));
        imagealphablending($canvas, true);

        $source = $this->platformArtwork();

        if ($source) {
            imagecopyresampled(
                $canvas,
                $source,
                0,
                0,
                0,
                0,
                $size,
                $size,
                imagesx($source),
                imagesy($source),
            );

            imagedestroy($source);
        }

        ob_start();
        imagepng($canvas);
        $bytes = (string) ob_get_clean();
        imagedestroy($canvas);

        return $bytes;
    }

    /**
     * The AkademicNest app icon, at the largest size available so any
     * downscale is a clean resample.
     *
     * A missing file leaves a plain brand-blue tile rather than a broken
     * image: an icon is not worth failing an install over.
     */
    private function platformArtwork(): ?\GdImage
    {
        foreach (self::ARTWORK as $relative) {
            $path = public_path($relative);

            if (! is_file($path)) {
                continue;
            }

            $image = @imagecreatefromstring((string) @file_get_contents($path));

            if ($image) {
                return $image;
            }
        }

        return null;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        ];
    }
}
