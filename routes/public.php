<?php

use App\Http\Controllers\CheckResultController;
use App\Http\Controllers\Guardian\Auth\AuthenticatedSessionController as GuardianAuthenticatedSessionController;
use App\Http\Controllers\IdCardVerificationController;
use App\Http\Controllers\Portal\AuthenticatedSessionController as PortalAuthenticatedSessionController;
use App\Http\Controllers\PublicSchoolWebsiteController;
use App\Http\Controllers\SchoolPortal\Auth\AuthenticatedSessionController as SchoolPortalAuthenticatedSessionController;
use App\Http\Controllers\SchoolPortalController;
use App\Http\Controllers\Staff\Auth\AuthenticatedSessionController as StaffAuthenticatedSessionController;
use App\Http\Controllers\Student\Auth\AuthenticatedSessionController as StudentAuthenticatedSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
|
| Everything reachable without signing in: the custom-domain mirror of the
| school-facing pages, the marketing site, result checking and ID-card
| verification.
|
*/

// Custom-domain mirror of the public routes below: the school is resolved from the
// Host header (via ResolveTenantFromCustomDomain) instead of a {school:slug} path
// segment, so every school-facing page can also be reached at its own domain. The
// {tenantDomain} wildcard excludes this app's own host so it can never shadow the
// app's own routes for normal requests. Registered BEFORE the default "/" route
// below (and every other host-agnostic route) because Laravel's router picks the
// FIRST route that matches a given method+URI regardless of domain specificity -
// a host-agnostic route registered earlier would otherwise always win over this
// domain-constrained one for the same "/" URI, even on a foreign host.
Route::group([
    'domain' => '{tenantDomain}',
    'where' => ['tenantDomain' => '^(?!'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost', '/').'$)(?!localhost$)(?!127\.0\.0\.1$).+$'],
    'middleware' => 'resolve_tenant_domain',
    'as' => 'tenant.',
], function () {
    Route::get('/', [PublicSchoolWebsiteController::class, 'show'])->name('school-website');
    Route::get('/news', [PublicSchoolWebsiteController::class, 'news'])->name('school-news.index');
    Route::get('/news/{post}', [PublicSchoolWebsiteController::class, 'newsShow'])->name('school-news.show');
    Route::get('/events', [PublicSchoolWebsiteController::class, 'events'])->name('school-events.index');
    Route::get('/careers', [PublicSchoolWebsiteController::class, 'careers'])->name('school-careers.index');
    Route::get('/gallery', [PublicSchoolWebsiteController::class, 'gallery'])->name('school-gallery.index');
    Route::get('/admissions', [PublicSchoolWebsiteController::class, 'admissions'])->name('school-admissions.index');
    Route::get('/facilities', [PublicSchoolWebsiteController::class, 'facilities'])->name('school-facilities.index');
    Route::get('/about', [PublicSchoolWebsiteController::class, 'about'])->name('school-about.index');
    Route::get('/contact', [PublicSchoolWebsiteController::class, 'contact'])->name('school-contact.index');

    // Mirrors the Portal hub and all four logins so a school reached at its
    // subdomain never has to jump back to the default "/schools/{slug}/..."
    // path just to sign in - same controllers as the default-path routes
    // below, reused exactly like PublicSchoolWebsiteController above. Every
    // store() already redirects with route(..., absolute: false), so once
    // signed in here the browser simply stays on this same host afterward.
    Route::get('/portal', [SchoolPortalController::class, 'index'])->name('portal.index');

    // The new unified portal login (Phase 2 of the portal-URL-security
    // rewrite) - additive, alongside the four per-guard logins below and the
    // portal.index hub above, which is why this can't claim the bare
    // "/portal" path yet. Phase 3 removes the hub/per-guard logins and
    // reclaims "/portal" for this. See
    // App\Http\Controllers\Portal\AuthenticatedSessionController.
    Route::get('/portal/sign-in', [PortalAuthenticatedSessionController::class, 'create'])->name('portal.show');
    Route::post('/portal/sign-in', [PortalAuthenticatedSessionController::class, 'store'])->name('portal.attempt');

    Route::middleware('portal_token')->group(function () {
        Route::name('student.')->group(function () {
            Route::get('/portal/{token}/login', [StudentAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/portal/{token}/login', [StudentAuthenticatedSessionController::class, 'store']);
        });

        Route::name('guardian.')->group(function () {
            Route::get('/parent-portal/{token}/login', [GuardianAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/parent-portal/{token}/login', [GuardianAuthenticatedSessionController::class, 'store']);
        });

        Route::name('staff.')->group(function () {
            Route::get('/staff-portal/{token}/login', [StaffAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/staff-portal/{token}/login', [StaffAuthenticatedSessionController::class, 'store']);
        });

        Route::name('portal.admin.')->group(function () {
            Route::get('/portal/admin/{token}/login', [SchoolPortalAuthenticatedSessionController::class, 'create'])->name('login');
            Route::post('/portal/admin/{token}/login', [SchoolPortalAuthenticatedSessionController::class, 'store']);
        });
    });
});

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
});

// Public, human-readable by design: schools share these links outside the platform.
Route::middleware('redirect_to_custom_domain')->name('public.')->group(function () {
    Route::get('/schools/{school:slug}', [PublicSchoolWebsiteController::class, 'show'])->name('school-website');
    Route::get('/schools/{school:slug}/news', [PublicSchoolWebsiteController::class, 'news'])->name('school-news.index');
    Route::get('/schools/{school:slug}/news/{post}', [PublicSchoolWebsiteController::class, 'newsShow'])->name('school-news.show');
    Route::get('/schools/{school:slug}/events', [PublicSchoolWebsiteController::class, 'events'])->name('school-events.index');
    Route::get('/schools/{school:slug}/careers', [PublicSchoolWebsiteController::class, 'careers'])->name('school-careers.index');
    Route::get('/schools/{school:slug}/gallery', [PublicSchoolWebsiteController::class, 'gallery'])->name('school-gallery.index');
    Route::get('/schools/{school:slug}/admissions', [PublicSchoolWebsiteController::class, 'admissions'])->name('school-admissions.index');
    Route::get('/schools/{school:slug}/facilities', [PublicSchoolWebsiteController::class, 'facilities'])->name('school-facilities.index');
    Route::get('/schools/{school:slug}/about', [PublicSchoolWebsiteController::class, 'about'])->name('school-about.index');
    Route::get('/schools/{school:slug}/contact', [PublicSchoolWebsiteController::class, 'contact'])->name('school-contact.index');
});

// Result-checking PINs: deliberately its own top-level group, not nested under
// the "public." website group above - it must keep working for Basic-plan
// schools, which are barred from the front-facing website entirely and have
// no SchoolWebsite record for that group's middleware/layout to depend on.
Route::prefix('schools/{school:slug}/check-result')->name('check-result.')->group(function () {
    Route::get('/', [CheckResultController::class, 'create'])->name('show');
    Route::post('/identify', [CheckResultController::class, 'identify'])->name('identify');
    Route::get('/confirm', [CheckResultController::class, 'confirm'])->name('confirm');
    Route::post('/', [CheckResultController::class, 'verify'])->name('verify');
    Route::get('/result/{usage}', [CheckResultController::class, 'result'])->name('result');
    Route::get('/result/{usage}/download', [CheckResultController::class, 'download'])->name('download');
});

// ID card QR verification: also deliberately ungated and unauthenticated -
// anyone scanning a printed card must be able to reach it without logging in.
// No plan-gating needed here: cards can only ever be issued by Standard/
// Exclusive schools (id_card_access guards generation, not this route).
Route::get('id-verify/{card:uuid}', [IdCardVerificationController::class, 'show'])->name('id-verify.show');
