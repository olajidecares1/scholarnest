<?php

namespace App\Http\Middleware\Api;

use App\Support\ApiAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps one kind of token out of another kind's endpoints.
 *
 * Sanctum tokens are polymorphic, so `auth:sanctum` alone answers "is this a
 * valid token?" and nothing else. A student's token is a perfectly valid token
 * at /guardian/children, and without this it would be accepted there and then
 * fall over somewhere further in, or worse, not fall over.
 *
 * Written as a route parameter (api.actor:student) rather than one class per
 * role, for the reason the plan-feature gate was rewritten: near-identical
 * middleware classes drift apart.
 */
class EnsureApiActorIs
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            in_array(ApiAccount::role($request->user()), $roles, true),
            403,
            'This endpoint is not available to your kind of account.',
        );

        return $next($request);
    }
}
