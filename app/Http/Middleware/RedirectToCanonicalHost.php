<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reaching the application by IP address sends you to its real address.
 *
 * Reaching it by IP (http://127.0.0.1:8000) and by name (http://lvh.me) served
 * the same site, and that is not merely untidy:
 *
 *   A SESSION COOKIE BELONGS TO ONE HOST. Sign in at 127.0.0.1:8000, then click
 *   any link the application generated - route() builds those from APP_URL, so
 *   they point at lvh.me - and the browser sends no cookie, because that is a
 *   different host. You are signed out, with nothing on screen explaining why.
 *
 *   The obfuscated admin paths make it harder to spot, not easier: the address
 *   you land on is 128 characters of hex, so "the host changed" is the last
 *   thing anyone notices about it.
 *
 * ONLY BARE IP ADDRESSES ARE REDIRECTED, and that narrowness is the whole
 * design. An unrecognised DOMAIN is not a wrong way of reaching this
 * application - it is a domain somebody has pointed here, which the tenant
 * routing answers with a 404 on purpose, and which this must not turn into a
 * redirect that confirms what is running at that address.
 *
 * A bare IP, by contrast, can never be a school's subdomain or a school's
 * custom domain. There is no case in which it is the right address, so there is
 * nothing to be careful about.
 */
class RedirectToCanonicalHost
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // GET and HEAD only. A redirect drops the body, so canonicalising a
        // POST would silently discard whatever was being submitted.
        if (! $request->isMethodSafe()) {
            return $next($request);
        }

        // Whatever monitors the machine hits this, usually at 127.0.0.1 and
        // often with something that does not follow redirects.
        if ($request->is('up')) {
            return $next($request);
        }

        $canonical = $this->canonicalHttpHost();
        $canonicalHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        // Two things get corrected, and nothing else:
        //
        //   the IP,   127.0.0.1:8000  ->  lvh.me
        //   the PORT, lvh.me:8000     ->  lvh.me
        //
        // The second is safe for the same reason the first is narrow: the host
        // name already matches, so there is no question of this being somebody
        // else's domain pointed here.
        $wrongPortOnTheRightHost = $canonicalHost !== null
            && $request->getHost() === $canonicalHost
            && $request->getHttpHost() !== $canonical;

        if (! $this->reachedByAddressRatherThanName($request->getHost()) && ! $wrongPortOnTheRightHost) {
            return $next($request);
        }

        // Nothing to send them to: APP_URL is unparseable, or is itself an IP -
        // which is a legitimate way to run this on a private network, and
        // redirecting 127.0.0.1 to another IP would help nobody.
        if ($canonical === null || $this->reachedByAddressRatherThanName(parse_url((string) config('app.url'), PHP_URL_HOST))) {
            return $next($request);
        }

        if ($request->getHttpHost() === $canonical) {
            return $next($request);
        }

        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: $request->getScheme();

        // 301, so a browser that has been going to the IP stops doing it on its
        // own rather than being corrected on every request for ever.
        return redirect()->away($scheme.'://'.$canonical.$request->getRequestUri(), 301);
    }

    /**
     * Did they type an address rather than a name?
     *
     * BARE IPs ONLY. "localhost" is deliberately not included: it is a real
     * hostname that plenty of setups use as APP_URL, and redirecting away from
     * it would be a surprise rather than a correction. An IP has no such claim -
     * nobody configures 127.0.0.1 as the address they want people to see.
     */
    private function reachedByAddressRatherThanName(?string $host): bool
    {
        if (! $host) {
            return false;
        }

        // Symfony wraps IPv6 hosts in brackets - [::1] - which filter_var does
        // not recognise as an IP until they are removed.
        return filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false;
    }

    /**
     * APP_URL's host and port, shaped the way getHttpHost() returns them - the
     * port omitted when it is the default for the scheme, so "http://lvh.me"
     * and a request to lvh.me on port 80 compare equal.
     */
    private function canonicalHttpHost(): ?string
    {
        $appUrl = (string) config('app.url');
        $host = parse_url($appUrl, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        $scheme = parse_url($appUrl, PHP_URL_SCHEME) ?: 'http';
        $port = parse_url($appUrl, PHP_URL_PORT);
        $default = $scheme === 'https' ? 443 : 80;

        return $port && $port !== $default ? "{$host}:{$port}" : $host;
    }
}
