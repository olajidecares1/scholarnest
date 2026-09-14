<?php

namespace App\Http\Middleware\Api;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A token outlives the account it was issued to, unless something checks.
 *
 * The session portals check is_active at sign-in, which is enough when a
 * session lasts hours. An API token lasts weeks: a student can be withdrawn, a
 * teacher can leave, a school's subscription can lapse, and the token in their
 * phone would go on working until it expired on its own.
 *
 * So the check moves from sign-in to every request. Both halves matter, the
 * account itself, and the school it belongs to, because deactivating a school
 * is how the platform stops serving it, and that has to reach its app users
 * too.
 *
 * 401 rather than 403: the credential is no longer good, and a client that
 * sees 401 already knows to sign in again.
 */
class EnsureApiAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $account = $request->user();

        abort_unless($account?->is_active, 401, 'This account is no longer active.');
        abort_unless($account->school?->is_active, 401, 'This school is no longer active.');

        return $next($request);
    }
}
