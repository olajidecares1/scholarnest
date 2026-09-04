<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Plain http, in production, is answered with a redirect and nothing else.
 *
 * URL::forceScheme('https') was already set, which makes every link the
 * application GENERATES https. It does nothing about a request that arrives
 * over http: that request is served normally, and a login form posted to it
 * carries the password in clear. Generating safe links and accepting unsafe
 * requests are two different things, and only the first was being done.
 *
 * A redirect cannot protect a password that has already been sent - the first
 * request is already on the wire before this runs - which is exactly why HSTS
 * matters alongside it (see SecurityHeaders). The redirect is what teaches the
 * browser to ask for HSTS in the first place; after that the browser stops
 * sending the plaintext request at all.
 *
 * Only in production. Local development is http, and a redirect there would
 * send every developer to a URL nothing is listening on.
 */
class RedirectToHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->secure() && app()->environment('production')) {
            // 301, so the browser stops asking over http on its own.
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
