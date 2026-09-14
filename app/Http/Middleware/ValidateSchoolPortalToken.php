<?php

namespace App\Http\Middleware;

use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gatekeeps the four school-scoped portal login pages behind a long, per-
 * school, per-portal token baked into the URL itself, knowing a school's
 * public slug/subdomain is no longer enough to reach its login forms.
 */
class ValidateSchoolPortalToken
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // The default-path routes bind {school:slug} as "school"; the
        // tenant-domain mirrors resolve it onto "tenantDomain" instead (see
        // ResolveTenantFromCustomDomain), exactly one of these is set for
        // any request this middleware runs on.
        $school = $request->route('school') ?? $request->route('tenantDomain');

        // Keyed off the controller action, not the route name, only the GET
        // half of each login pair is actually named ("student.login" etc.),
        // the POST half isn't, so getName() would be null for every submit.
        $action = (string) $request->route()?->getActionName();

        $column = match (true) {
            str_contains($action, '\\Student\\Auth\\') => 'portal_student_token',
            str_contains($action, '\\Guardian\\Auth\\') => 'portal_guardian_token',
            str_contains($action, '\\Staff\\Auth\\') => 'portal_staff_token',
            str_contains($action, '\\SchoolPortal\\Auth\\') => 'portal_admin_token',
            default => null,
        };

        $token = (string) $request->route('token');
        $expected = $school instanceof School && $column ? (string) $school->{$column} : null;

        abort_unless($expected && hash_equals($expected, $token), 404);

        return $next($request);
    }
}
