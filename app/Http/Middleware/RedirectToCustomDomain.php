<?php

namespace App\Http\Middleware;

use App\Enums\CustomDomainStatus;
use App\Enums\PlanKey;
use App\Models\School;
use App\Support\TenantUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * When a school's public site is reached via the default /schools/{slug}
 * path but a better tenant domain applies - a verified Exclusive custom
 * domain with redirects enabled, or a Standard school's free ednumest.com
 * subdomain - permanently redirect to the equivalent page there instead of
 * rendering the default-path page.
 */
class RedirectToCustomDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $school = $request->route('school');
        $routeName = $request->route()?->getName();

        if (! $school instanceof School || ! $routeName || ! str_starts_with($routeName, 'public.')) {
            return $next($request);
        }

        $host = $this->resolveRedirectHost($school);

        if (! $host) {
            return $next($request);
        }

        $tenantRouteName = 'tenant.'.Str::after($routeName, 'public.');

        if (! Route::has($tenantRouteName)) {
            return $next($request);
        }

        $params = collect($request->route()->parameters())
            ->except('school')
            ->all();

        // route() infers scheme/port from how the CURRENT request arrived, which is
        // wrong here (tenant domains use this app's configured scheme/port from
        // APP_URL, not whatever host the default-path request happened to arrive
        // on) - generate with a throwaway host, then keep only the path/query and
        // rebuild the URL against the real domain.
        $generated = route($tenantRouteName, [...$params, 'tenantDomain' => 'akademicnest-placeholder-host.invalid']);
        $path = parse_url($generated, PHP_URL_PATH) ?? '/';
        $query = parse_url($generated, PHP_URL_QUERY);

        $url = TenantUrl::build($host, $path, $query);

        return redirect()->away($url, 301);
    }

    /**
     * Unlike Exclusive's custom domain (which has an explicit
     * redirect_default_domain toggle, since a school may want to verify it
     * works before forcing visitors over), a Standard school's subdomain has
     * no such opt-out - it's the only address it has, so the redirect is
     * unconditional once TENANT_BASE_DOMAIN is configured.
     */
    private function resolveRedirectHost(School $school): ?string
    {
        if ($school->hasPlanAccess(PlanKey::Exclusive)) {
            $primary = $school->primaryCustomDomain;

            if ($primary && $primary->status === CustomDomainStatus::Verified && $primary->redirect_default_domain) {
                return $primary->domain;
            }

            return null;
        }

        $baseDomain = config('custom_domain.tenant_base_domain');

        // The subdomain column, not the slug - it has no hyphens in it, and it
        // must be the same value ResolveTenantFromCustomDomain looks up by, or
        // this redirects to an address that then 404s.
        return $baseDomain && $school->hasPlanAccess(PlanKey::Standard) ? "{$school->subdomain}.{$baseDomain}" : null;
    }
}
