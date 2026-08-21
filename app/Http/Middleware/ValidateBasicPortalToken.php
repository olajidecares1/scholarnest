<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatekeeps the Basic-plan portal entry point behind the 32-character token
 * baked into its URL, mirroring how ValidateSchoolPortalToken guards the four
 * school-scoped logins.
 *
 * Unlike those, this token is platform-wide rather than per-school: the Basic
 * portal is one shared front door for every Basic school, and the school is
 * only identified afterwards, by name.
 *
 * A wrong or missing token gives 404, never 403. A 403 would confirm that
 * something real sits at this path and invite guessing at it; a 404 says
 * nothing at all.
 */
class ValidateBasicPortalToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('basic_portal.token');
        $supplied = (string) $request->route('token');

        // Fail closed. With no token configured the portal does not exist,
        // rather than falling back to something predictable.
        abort_if($expected === '', 404);

        // hash_equals rather than ===, so the comparison takes the same time
        // whether the first character is wrong or only the last one is.
        abort_unless(hash_equals($expected, $supplied), 404);

        return $next($request);
    }
}
