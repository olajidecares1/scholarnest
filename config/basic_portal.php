<?php

/*
|--------------------------------------------------------------------------
| Basic-plan portal
|--------------------------------------------------------------------------
|
| Basic-plan schools have no public website and no subdomain of their own, so
| they cannot be reached the way Standard and Exclusive schools are. They come
| in through a single shared entry point instead:
|
|     /{32-character token}   ->   type your school name
|                                    ->   /{school-slug}
|
| This flow is deliberately separate from the Standard and Exclusive portals.
| See docs/BASIC-PLAN-PORTAL.md.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Entry token
    |--------------------------------------------------------------------------
    |
    | The 32-character token in the Basic portal URL. It is not a password and
    | it identifies no one - it simply keeps the entry point from being found
    | by anyone idly typing /portal, in the same spirit as the per-school
    | portal tokens already used elsewhere in the app.
    |
    | There is deliberately NO default. If this is unset, every Basic portal
    | URL returns 404 rather than silently accepting a predictable token that
    | shipped in the source code.
    |
    | Generate one with:
    |
    |     php artisan basic-portal:token
    |
    */

    'token' => env('BASIC_PORTAL_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Reserved first-path segments
    |--------------------------------------------------------------------------
    |
    | A Basic school is reached at the root of the site: akademicanest.com/greenfield-
    | college. That means a school slug occupies the same namespace as the
    | application's own top-level paths, so a school must never be allowed a
    | slug that collides with one.
    |
    | The route itself is registered last, so a real route always wins on a
    | collision. This list is the second line of defence, and it is also what
    | school registration checks so the clash cannot be created in the first
    | place.
    |
    */

    'reserved_slugs' => [
        // Application entry points
        'portal', 'login', 'logout', 'register', 'dashboard', 'schools', 'school',
        'admin', 'administrator', 'super-admin', 'superadmin', 'account', 'accounts',
        'profile', 'settings', 'password', 'verify', 'reset', 'auth', 'oauth', 'sso',

        // Commerce
        'billing', 'pay', 'payment', 'payments', 'checkout', 'subscribe',
        'subscription', 'subscriptions', 'plans', 'pricing', 'invoice', 'invoices',

        // Platform features
        'reports', 'report', 'id-verify', 'check-result', 'storage', 'media',
        'files', 'uploads', 'download', 'downloads', 'search', 'notifications',

        // Infrastructure
        'api', 'graphql', 'cdn', 'assets', 'static', 'img', 'images', 'css', 'js',
        'fonts', 'build', 'vendor', 'health', 'up', 'status', 'metrics', 'telescope',
        'horizon', 'pulse', 'livewire', 'sanctum', 'broadcasting',

        // Mail and well-known names, which enable convincing phishing
        'mail', 'email', 'smtp', 'webmail', 'noreply', 'no-reply', 'postmaster',
        'abuse', 'security', 'www', 'ns', 'ns1', 'ns2', 'dns', 'ftp', 'well-known',

        // Environments
        'dev', 'development', 'test', 'testing', 'stage', 'staging', 'demo',
        'sandbox', 'preview', 'beta', 'local', 'localhost',

        // Company pages
        'about', 'contact', 'help', 'support', 'docs', 'blog', 'news', 'careers',
        // EVERY name this platform has traded under, and both spellings of the
        // current one: the brand is AkademicNest and the domain is
        // akademicanest.com, so a school could otherwise claim whichever of
        // the two this list forgot. Old links and old printed material still
        // carry the earlier names, so those stay denied too.
        'legal', 'privacy', 'terms', 'press', 'partners',
        'akademicnest', 'akademicanest', 'scholarnest', 'edunest',
    ],

];
