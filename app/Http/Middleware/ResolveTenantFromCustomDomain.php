<?php

namespace App\Http\Middleware;

use App\Services\Tenancy\TenantResolution;
use App\Services\Tenancy\TenantResolver;
use App\Support\CurrentTenant;
use App\Support\TenantUrl;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which School a request on a tenant host belongs to, then injects it
 * as the route's parameter, so the same controllers that serve the default
 * /p/{portal_key} paths serve school subdomains and custom domains too, with no
 * controller duplication.
 *
 *   greenfield.akademicanest.com      Standard or Exclusive school's subdomain
 *   greenfieldschool.com              Exclusive school's verified own domain
 *
 * The decision itself lives in {@see TenantResolver}; this only acts on it:
 *
 *   resolved       bind the school and carry on
 *   alias          301 to the school's canonical subdomain
 *   www            301 to the platform
 *   unavailable    a status page (suspended, expired, pending, or Basic)
 *   unknown        404
 */
class ResolveTenantFromCustomDomain
{
    public function __construct(
        private readonly TenantResolver $resolver,
        private readonly CurrentTenant $tenant,
    ) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $resolution = $this->resolver->resolve($request->getHost());

        if ($resolution->status === TenantResolution::PLATFORM_ALIAS) {
            return redirect()->away(rtrim((string) config('app.url'), '/').$request->getRequestUri(), 301);
        }

        // A real school, reached at its slug or at the hyphenated spelling of
        // its subdomain. Send it to the one canonical address, keeping the
        // path and query, so a link printed with the older spelling still
        // lands on the right page rather than on the school's front door.
        if ($resolution->status === TenantResolution::ALIAS && $resolution->school) {
            $canonical = $resolution->school->subdomainHost();

            if ($canonical !== null) {
                return redirect()->away(
                    TenantUrl::build($canonical, $request->getPathInfo(), $request->getQueryString()),
                    // 308 for anything with a body. A 301 tells the browser it
                    // may repeat the request as a GET, which silently throws
                    // away a submitted form; 308 keeps the method and the body.
                    $request->isMethodSafe() ? 301 : 308,
                );
            }
        }

        if (! $resolution->resolved()) {
            return $this->unavailable($resolution);
        }

        $school = $resolution->school;

        // Replace the wildcard "tenantDomain" parameter with the resolved School
        // rather than adding a separate "school" parameter alongside it. Laravel
        // passes route parameters positionally, so leaving the raw host string
        // in place would hand it to the controller's School argument.
        $request->route()->setParameter('tenantDomain', $school);

        $this->tenant->set($school);
        $request->attributes->set('tenant', $school);
        View::share('currentTenant', $school);

        return $next($request);
    }

    private function unavailable(TenantResolution $resolution): Response
    {
        // An unknown address and a Basic school's address both answer 404: a
        // Basic school has no website, so there is nothing at that address to
        // be "unavailable". A real website that is paused answers 503, which
        // tells search engines the pause is temporary and not to drop it.
        $status = match ($resolution->reason) {
            'suspended', 'subscription' => 503,
            default => 404,
        };

        return response()->view('errors.tenant-unavailable', [
            'reason' => $status === 404 ? 'not-found' : $resolution->reason,
            'platformUrl' => rtrim((string) config('app.url'), '/'),
        ], $status)->header('Cache-Control', 'no-store, private');
    }
}
