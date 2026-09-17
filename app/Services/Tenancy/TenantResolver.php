<?php

namespace App\Services\Tenancy;

use App\Enums\CustomDomainStatus;
use App\Enums\PlanKey;
use App\Models\CustomDomain;
use App\Models\School;

/**
 * Which school, if any, a hostname belongs to.
 *
 * The ONE place that answers this. The tenant middleware, the portal
 * sign-in redirect and the account/host check all ask here, so there is no
 * second copy of the rules to drift out of step (an older copy matched the
 * slug, with its hyphens, against the host and never found anyone).
 *
 *   akademicanest.com                   the platform, never a tenant
 *   www.akademicanest.com               a platform alias, sent to the platform
 *   greenfield.akademicanest.com        the school whose `subdomain` is greenfield
 *   greenfieldschool.com                an Exclusive school's verified custom domain
 *
 * Plans, as sold:
 *
 *   BASIC       no website, so no subdomain is served
 *   STANDARD    its subdomain
 *   EXCLUSIVE   its subdomain, and its own domain once verified
 *
 * Only the host decides. Nothing the browser sends in a form or a query string
 * is consulted, so a school_id cannot be swapped to reach another school.
 */
class TenantResolver
{
    /**
     * Labels under the base domain that belong to the platform, not a school.
     * They are also refused when a subdomain is generated or edited.
     */
    public const PLATFORM_ALIASES = ['www'];

    /**
     * Answers already given in this request, by host. The resolver is a
     * scoped binding, so this is emptied between requests and a school's
     * status is never remembered past the request it was read in.
     *
     * @var array<string, TenantResolution>
     */
    private array $resolved = [];

    public function resolve(string $host): TenantResolution
    {
        $host = $this->normalise($host);

        return $this->resolved[$host] ??= $this->lookup($host);
    }

    private function lookup(string $host): TenantResolution
    {

        if ($host === '' || $host === $this->centralHost()) {
            return new TenantResolution(TenantResolution::CENTRAL);
        }

        $base = $this->baseDomain();

        if ($base !== null && ($host === $base)) {
            // The base domain differs from APP_URL's host (e.g. a staging
            // APP_URL): the bare base is still the platform, never a school.
            return new TenantResolution(TenantResolution::CENTRAL);
        }

        if ($base !== null && str_ends_with($host, ".{$base}")) {
            return $this->resolveSubdomain(substr($host, 0, -(strlen($base) + 1)));
        }

        return $this->resolveCustomDomain($host);
    }

    /**
     * The school a host resolves to AND may be served, or null.
     */
    public function schoolFor(string $host): ?School
    {
        $resolution = $this->resolve($host);

        return $resolution->resolved() ? $resolution->school : null;
    }

    /**
     * The base domain school subdomains live under, lower-cased, or null when
     * subdomains are switched off (TENANT_BASE_DOMAIN unset).
     */
    public function baseDomain(): ?string
    {
        $base = $this->normalise((string) config('custom_domain.tenant_base_domain'));

        return $base === '' ? null : $base;
    }

    public function centralHost(): string
    {
        return $this->normalise((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    /**
     * May this school's website be served on its platform subdomain?
     */
    public static function planIncludesSubdomain(School $school): bool
    {
        return $school->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive);
    }

    private function resolveSubdomain(string $label): TenantResolution
    {
        if (in_array($label, self::PLATFORM_ALIASES, true)) {
            return new TenantResolution(TenantResolution::PLATFORM_ALIAS, via: 'subdomain');
        }

        // One DNS label only. a.b.akademicanest.com is not a school address,
        // and a label outside [a-z0-9] cannot be one either, so neither costs a
        // database query.
        if (! preg_match('/^[a-z0-9]{1,63}$/', $label)) {
            return new TenantResolution(TenantResolution::NOT_FOUND, via: 'subdomain');
        }

        // Looked up by the subdomain column, which is what School::publicUrl()
        // builds the address from. Never the slug: it has hyphens and the
        // address deliberately does not.
        $school = School::with(['activeSubscription.plan', 'website'])->where('subdomain', $label)->first();

        if (! $school) {
            return new TenantResolution(TenantResolution::NOT_FOUND, via: 'subdomain');
        }

        return $this->gate($school, 'subdomain', fn (School $s) => self::planIncludesSubdomain($s));
    }

    private function resolveCustomDomain(string $host): TenantResolution
    {
        $domain = CustomDomain::with(['school.activeSubscription.plan', 'school.website'])
            ->where('domain', $host)
            ->where('status', CustomDomainStatus::Verified)
            ->first();

        if (! $domain || ! $domain->school) {
            return new TenantResolution(TenantResolution::NOT_FOUND, via: 'custom-domain');
        }

        // Re-checked at request time, not just when the domain was set up, so
        // a school that downgrades from Exclusive, or whose subscription
        // lapses, stops serving its old custom domain.
        return $this->gate($domain->school, 'custom-domain', fn (School $s) => $s->hasPlanAccess(PlanKey::Exclusive));
    }

    /**
     * @param  callable(School): bool  $planAllows
     */
    private function gate(School $school, string $via, callable $planAllows): TenantResolution
    {
        if (! $school->is_active) {
            return new TenantResolution(TenantResolution::UNAVAILABLE, $school, $via, 'suspended');
        }

        // No approved subscription: expired, rejected, or still awaiting
        // approval. The school's data is untouched; only the site is paused.
        if ($school->activeSubscription === null) {
            return new TenantResolution(TenantResolution::UNAVAILABLE, $school, $via, 'subscription');
        }

        if (! $planAllows($school)) {
            return new TenantResolution(TenantResolution::UNAVAILABLE, $school, $via, 'plan');
        }

        return new TenantResolution(TenantResolution::RESOLVED, $school, $via);
    }

    private function normalise(string $host): string
    {
        return rtrim(strtolower(trim($host)), '.');
    }
}
