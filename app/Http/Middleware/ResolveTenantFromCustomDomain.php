<?php

namespace App\Http\Middleware;

use App\Enums\CustomDomainStatus;
use App\Enums\PlanKey;
use App\Models\CustomDomain;
use App\Models\School;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which School a request on a tenant domain belongs to, then
 * injects it as the route's "school" parameter - letting the exact same
 * PublicSchoolWebsiteController methods (which already expect School $school
 * from the path-based {school:slug} routes) serve domain-based requests too,
 * with no controller duplication. Two kinds of tenant domain are handled
 * here: an Exclusive school's own verified custom domain, and a Standard
 * school's free {slug}.{TENANT_BASE_DOMAIN} subdomain.
 */
class ResolveTenantFromCustomDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $school = $this->resolveFromCustomDomain($host) ?? $this->resolveFromSubdomain($host);

        abort_unless($school, 404);

        // Replace the wildcard "tenantDomain" parameter with the resolved School
        // rather than adding a separate "school" parameter alongside it - leaving
        // both in place means the route ends up with two parameters for a
        // controller action that only takes one, and Laravel's ControllerDispatcher
        // passes route parameters positionally, so the raw domain string would be
        // passed as the first (and wrongly typed) argument.
        $request->route()->setParameter('tenantDomain', $school);

        return $next($request);
    }

    private function resolveFromCustomDomain(string $host): ?School
    {
        // Eager-loads the chain hasPlanAccess() needs below so it doesn't run a
        // second query - this middleware runs on every request to a custom
        // domain, so it stays a single indexed lookup either way.
        $domain = CustomDomain::with('school.activeSubscription.plan')
            ->where('domain', $host)
            ->where('status', CustomDomainStatus::Verified)
            ->first();

        if (! $domain) {
            return null;
        }

        $school = $domain->school;

        // Re-checked at request time, not just when the domain was set up -
        // otherwise a school that downgrades from Exclusive, or whose
        // subscription lapses, would keep silently serving its old custom
        // domain forever.
        return $school && $school->is_active && $school->hasPlanAccess(PlanKey::Exclusive) ? $school : null;
    }

    private function resolveFromSubdomain(string $host): ?School
    {
        $baseDomain = config('custom_domain.tenant_base_domain');

        if (! $baseDomain || ! str_ends_with($host, ".{$baseDomain}")) {
            return null;
        }

        $label = Str::beforeLast($host, ".{$baseDomain}");

        // Looked up by the subdomain column, which is what School::publicUrl()
        // and RedirectToCustomDomain both build the address from. It is not the
        // slug: the slug has hyphens and the address deliberately does not, so
        // matching on it here would 404 every school with more than one word in
        // its name.
        $school = School::with('activeSubscription.plan')->where('subdomain', $label)->first();

        return $school && $school->is_active && $school->hasPlanAccess(PlanKey::Standard) ? $school : null;
    }
}
