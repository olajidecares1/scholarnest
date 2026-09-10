<?php

return [
    /*
    |--------------------------------------------------------------------------
    | DNS Routing Target
    |--------------------------------------------------------------------------
    |
    | The host a school's custom domain must CNAME to (or, for apex domains
    | whose registrar has no ALIAS/ANAME/CNAME-flattening option, the IP a
    | plain A record must point to) for traffic to actually reach AkademicNest.
    | Defaults to this application's own host so it never needs to be set
    | explicitly in local/staging environments.
    |
    */
    'cname_target' => env('CUSTOM_DOMAIN_CNAME_TARGET', parse_url(env('APP_URL', 'http://localhost'), PHP_URL_HOST)),

    'a_record_ip' => env('CUSTOM_DOMAIN_A_RECORD_IP'),

    /*
    |--------------------------------------------------------------------------
    | Ownership Verification
    |--------------------------------------------------------------------------
    |
    | The subdomain prefix a school must add a TXT record to (containing
    | their unique verification token) to prove they control the domain
    | before we route traffic to it or issue an SSL certificate for it.
    |
    */
    'txt_verification_prefix' => env('CUSTOM_DOMAIN_TXT_PREFIX', '_akademicnest-verify'),

    /*
     * Prefixes from before the rename, still ACCEPTED but never advertised.
     *
     * The prefix above is what a school is told to create. These are what
     * schools created under the platform's old names, at their own registrar,
     * on domains this application does not control. Renaming the prefix alone
     * would silently un-verify every one of them until somebody noticed and
     * edited their own DNS, so verification tries these too and the wizard
     * shows only the current one.
     *
     * Safe to empty once no verified domain relies on an old prefix.
     */
    'legacy_txt_verification_prefixes' => ['_edunest-verify', '_scholarnest-verify'],

    /*
    |--------------------------------------------------------------------------
    | Automatic Re-Verification Interval
    |--------------------------------------------------------------------------
    |
    | How often the scheduler re-checks domains that are not yet verified,
    | in minutes, so DNS changes a school makes are picked up automatically
    | without them needing to click "Verify Now" again.
    |
    */
    'auto_verify_interval_minutes' => (int) env('CUSTOM_DOMAIN_AUTO_VERIFY_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Subdomain Base Domain
    |--------------------------------------------------------------------------
    |
    | The base domain Standard-plan schools are given a free subdomain under
    | (e.g. "akademicanest.com" -> "greenhill.akademicanest.com"). Left unset in local/
    | staging environments so ResolveTenantFromCustomDomain and
    | RedirectToCustomDomain never try to resolve or redirect to a domain
    | that doesn't actually point at this app - the feature only activates
    | once this is explicitly configured in production, alongside real
    | wildcard DNS and a wildcard TLS certificate for it (neither of which
    | this application can set up on its own).
    |
    */
    'tenant_base_domain' => env('TENANT_BASE_DOMAIN'),
];
