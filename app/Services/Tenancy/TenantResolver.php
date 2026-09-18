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
        // and anything outside the characters a hostname label may contain
        // cannot be one either, so neither costs a database query.
        //
        // HYPHENS ARE ADMITTED HERE, and they were not before. The canonical
        // address has none, see School::availableSubdomain(), but the school's
        // slug does, and the hyphenated form is the one that gets typed:
        // it is the school's address on the platform host, so it is what
        // people copy, shorten and print. Refusing it at the pattern meant
        // "vincent-martins-college.akademicanest.com" never reached a query at
        // all and 404'd, while a one-word school, whose slug and subdomain are
        // the same string, worked. That is exactly the shape of "it works for
        // some schools and not others".
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $label)) {
            return new TenantResolution(TenantResolution::NOT_FOUND, via: 'subdomain');
        }

        // Looked up by the subdomain column, which is what School::publicUrl()
        // builds the address from.
        $school = School::with(['activeSubscription.plan', 'website'])->where('subdomain', $label)->first();

        if ($school) {
            return $this->gate($school, 'subdomain', fn (School $s) => self::planIncludesSubdomain($s));
        }

        return $this->resolveSubdomainAlias($label);
    }

    /**
     * A school reached at a label that is not its canonical subdomain.
     *
     * Two of them, and both are addresses the application itself has handed
     * out at one time or another:
     *
     *   vincent-martins-college   the slug, the school's address on the
     *                             platform host, and what anybody who knows
     *                             the school's web address would try first
     *   vincentmartinscollege     the canonical subdomain, handled above
     *
     * A school whose subdomain is NULL is reached this way too, which is what
     * saves any row the backfill in add_subdomain_to_schools_table never
     * reached: its slug still finds it, and `schools:subdomains --repair`
     * fills the column in.
     *
     * Both lookups are single indexed reads on unique columns, so an unknown
     * address still costs two queries and nothing more.
     *
     * The answer is a REDIRECT, never a second address serving the same site:
     * one school, one canonical host, so sessions, cookies, cached pages and
     * search results cannot fragment across two spellings of it.
     */
    private function resolveSubdomainAlias(string $label): TenantResolution
    {
        $stripped = preg_replace('/[^a-z0-9]/', '', $label);

        $school = $stripped !== '' && $stripped !== $label
            ? School::with(['activeSubscription.plan', 'website'])->where('subdomain', $stripped)->first()
            : null;

        $school ??= School::with(['activeSubscription.plan', 'website'])->where('slug', $label)->first();

        if (! $school) {
            return new TenantResolution(TenantResolution::NOT_FOUND, via: 'subdomain');
        }

        // Gated first. A suspended school, a lapsed subscription or a Basic
        // school must answer exactly as it does on its canonical address,
        // never be redirected to an address that then says something else.
        $gated = $this->gate($school, 'subdomain', fn (School $s) => self::planIncludesSubdomain($s));

        if (! $gated->resolved()) {
            return $gated;
        }

        $canonical = $school->subdomainHost();

        // No canonical host to send them to (the column is empty and the
        // repair command has not run): serve it here rather than 404, which
        // is the whole point of finding it by slug.
        return $canonical === null
            ? $gated
            : new TenantResolution(TenantResolution::ALIAS, $school, 'subdomain');
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
