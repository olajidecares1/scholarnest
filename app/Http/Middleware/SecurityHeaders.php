<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The headers that tell a browser what this application is allowed to do.
 *
 * Everything here is defence the server cannot perform on its own: only the
 * browser can refuse to frame the page, refuse to guess a file's type, or
 * refuse to run a script from somewhere it should not. Sending nothing leaves
 * every one of those decisions to the browser's defaults, which are permissive
 * by design.
 *
 * The Content-Security-Policy below is deliberately not the strictest one that
 * could be written, and it is worth being precise about why rather than
 * implying it is airtight:
 *
 *   script-src keeps 'unsafe-inline' and 'unsafe-eval'. Alpine evaluates the
 *   expressions written in x-data and x-show attributes, which is eval by
 *   another name, and every layout carries a small inline script that applies
 *   the saved dark-mode preference before first paint, deliberately inline,
 *   because loading it separately is what causes the white flash. A policy
 *   that forbade either would break the application, and a broken policy gets
 *   removed rather than fixed.
 *
 *   style-src keeps 'unsafe-inline' because each school's brand colour is
 *   emitted as an inline <style> block computed per request.
 *
 * What the policy does buy is real, and is the part that is not weakened:
 * object-src 'none' kills legacy plugin embedding, base-uri 'self' stops an
 * injected <base> redirecting every relative URL on the page, form-action
 * 'self' stops an injected form posting a password somewhere else, and
 * frame-ancestors 'none' makes clickjacking impossible regardless of what any
 * older X-Frame-Options parsing does.
 */
class SecurityHeaders
{
    /**
     * One year, the minimum any HSTS preload list will accept.
     */
    private const HSTS_MAX_AGE = 31536000;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Nothing in this application uses a camera, a microphone or a
        // location, so nothing embedded in it should be able to ask.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()'
        );

        $response->headers->set('Content-Security-Policy', $this->contentSecurityPolicy());

        // HSTS only over https, and only in production. Sent from a laptop it
        // would pin http://localhost to https for a year in the developer's
        // own browser, a self-inflicted outage that survives clearing the
        // cache and is genuinely awkward to undo.
        if ($request->secure() && app()->environment('production')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age='.self::HSTS_MAX_AGE.'; includeSubDomains'
            );
        }

        return $response;
    }

    /**
     * The scheme and host uploaded files are addressed from.
     *
     * Empty when APP_URL is unparseable, which simply leaves the directive as
     * 'self' rather than emitting a malformed policy.
     */
    private function applicationOrigin(): string
    {
        $url = (string) config('app.url');

        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);

        if (! $scheme || ! $host) {
            return '';
        }

        $port = parse_url($url, PHP_URL_PORT);

        return $scheme.'://'.$host.($port ? ':'.$port : '');
    }

    private function contentSecurityPolicy(): string
    {
        return implode('; ', [
            "default-src 'self'",

            // See the note above: Alpine needs both of these.
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",

            // Per-school brand colours are inline, and the two font hosts
            // serve the stylesheets the layouts link.
            "style-src 'self' 'unsafe-inline' https://fonts.bunny.net https://fonts.googleapis.com",
            "font-src 'self' data: https://fonts.bunny.net https://fonts.gstatic.com",

            // data: and blob: because barcodes, QR codes and ID-card artwork
            // are generated in the request and never written to disk.
            //
            // The application's own origin is named as well as 'self', and
            // that is not redundant here: every school is served from its own
            // subdomain or custom domain, while uploaded logos and photographs
            // are addressed from APP_URL. To a browser on the school's domain
            // those are a different origin, so 'self' alone blocked every
            // crest and pupil photograph on every tenant domain in the
            // application. The API hands the same absolute URLs to mobile
            // clients, so making them relative was not an option.
            "img-src 'self' data: blob: ".$this->applicationOrigin(),

            "connect-src 'self'",
            "media-src 'self' data: blob:",

            // The parts that are not weakened for anybody's convenience.
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }
}
