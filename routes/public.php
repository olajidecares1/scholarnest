<?php

use App\Http\Controllers\CheckResultController;
use App\Http\Controllers\Guardian\Auth\AuthenticatedSessionController as GuardianAuthenticatedSessionController;
use App\Http\Controllers\IdCardVerificationController;
use App\Http\Controllers\LegalDocumentController;
use App\Http\Controllers\Portal\AuthenticatedSessionController as PortalAuthenticatedSessionController;
use App\Http\Controllers\ProtectedMediaController;
use App\Http\Controllers\PublicContactMessageController;
use App\Http\Controllers\PublicMisconductReportController;
use App\Http\Controllers\PublicSchoolWebsiteController;
use App\Http\Controllers\RegistrationResumeController;
use App\Http\Controllers\SchoolPortal\Auth\AuthenticatedSessionController as SchoolPortalAuthenticatedSessionController;
use App\Http\Controllers\SchoolPortalController;
use App\Http\Controllers\Staff\Auth\AuthenticatedSessionController as StaffAuthenticatedSessionController;
use App\Http\Controllers\Student\Auth\AuthenticatedSessionController as StudentAuthenticatedSessionController;
use App\Http\Controllers\SubscriptionInvoiceController;
use App\Models\LegalDocument;
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
// Host header (via ResolveTenantFromCustomDomain) instead of a {school:portal_key} path
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

    // Open to anyone: the people who report a pupil's conduct outside
    // school are neighbours and shopkeepers, not account holders. Rate
    // limited and honeypotted because an open endpoint that writes files
    // is the first thing a bot finds.
    Route::middleware(['throttle:6,60', 'honeypot'])
        ->post('/conduct-report', [PublicMisconductReportController::class, 'store'])
        ->name('misconduct-report.store');

    Route::middleware(['throttle:10,60', 'honeypot'])
        ->post('/contact-message', [PublicContactMessageController::class, 'store'])
        ->name('contact-message.store');

    // Mirrors the Portal hub and all four logins so a school reached at its
    // subdomain never has to jump back to the default "/p/{portal_key}/..."
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

// Photographs of people, from PRIVATE storage.
//
// The `signed` middleware is the access control: the URL carries a signature
// minted by whatever rendered the page, and an altered, forged or expired one
// never reaches the controller. A session check could not be used here because
// two of the pages that legitimately show a photograph have no session - a
// parent opening a result with a token, and somebody scanning the QR on a
// printed ID card. See App\Http\Controllers\ProtectedMediaController.
Route::get('media/{subject}/{uuid}', [ProtectedMediaController::class, 'photo'])
    ->middleware('signed')
    ->whereIn('subject', ['student', 'staff', 'guardian', 'user'])
    ->name('media.photo');

// The invoice link in a billing email.
//
// Signed rather than gated, for the same reason photographs are: a billing
// email is commonly opened by a bursar or a proprietor who has no account on
// this platform, and an invoice they cannot open has not really been sent.
// The signature expires - see App\Notifications\SubscriptionInvoiceIssuedNotification.
Route::get('invoices/{invoice}', [SubscriptionInvoiceController::class, 'view'])
    ->middleware('signed')
    ->name('invoices.view');

// "Continue your registration", from the reminder email. Signed, and
// deliberately not a sign-in - see App\Http\Controllers\RegistrationResumeController.
Route::get('continue-registration/{school}', RegistrationResumeController::class)
    ->middleware('signed')
    ->name('registration.resume');

// AkademicNest's own legal documents. Open to anyone, and readable BEFORE
// registering: they are linked from the agreement checkbox on the registration
// form, and a school cannot meaningfully agree to terms it can only read after
// it has an account.
//
// Plain, readable paths rather than obfuscated ones - unlike the dashboard
// these are meant to be linked to, shared and cited. The {document} segment is
// constrained to the documents that exist, so a slug can never reach the audit
// and gap report that sit in the same directory.
Route::name('legal.')->prefix('legal')->group(function () {
    Route::get('/', [LegalDocumentController::class, 'index'])->name('index');
    Route::get('/{document}', [LegalDocumentController::class, 'show'])
        // The fixed list on the model, not a database query: the constraint is
        // built at boot, and a slug is therefore incapable of naming anything
        // else - including the audit and gap report in the same source
        // directory, which have no row and no slug.
        ->whereIn('document', LegalDocument::SLUGS)
        ->name('show');
});

// Public, human-readable by design: schools share these links outside the platform.
Route::middleware('redirect_to_custom_domain')->name('public.')->group(function () {
    Route::get('/p/{school:portal_key}', [PublicSchoolWebsiteController::class, 'show'])->name('school-website');
    Route::get('/p/{school:portal_key}/news', [PublicSchoolWebsiteController::class, 'news'])->name('school-news.index');
    Route::get('/p/{school:portal_key}/news/{post}', [PublicSchoolWebsiteController::class, 'newsShow'])->name('school-news.show');
    Route::get('/p/{school:portal_key}/events', [PublicSchoolWebsiteController::class, 'events'])->name('school-events.index');
    Route::get('/p/{school:portal_key}/careers', [PublicSchoolWebsiteController::class, 'careers'])->name('school-careers.index');
    Route::get('/p/{school:portal_key}/gallery', [PublicSchoolWebsiteController::class, 'gallery'])->name('school-gallery.index');
    Route::get('/p/{school:portal_key}/admissions', [PublicSchoolWebsiteController::class, 'admissions'])->name('school-admissions.index');
    Route::get('/p/{school:portal_key}/facilities', [PublicSchoolWebsiteController::class, 'facilities'])->name('school-facilities.index');
    Route::get('/p/{school:portal_key}/about', [PublicSchoolWebsiteController::class, 'about'])->name('school-about.index');
    Route::get('/p/{school:portal_key}/contact', [PublicSchoolWebsiteController::class, 'contact'])->name('school-contact.index');

    // The same open endpoint as the tenant group above, for schools
    // reached at the default /p/{portal_key} path rather than a domain.
    Route::middleware(['throttle:6,60', 'honeypot'])
        ->post('/p/{school:portal_key}/conduct-report', [PublicMisconductReportController::class, 'store'])
        ->name('misconduct-report.store');

    Route::middleware(['throttle:10,60', 'honeypot'])
        ->post('/p/{school:portal_key}/contact-message', [PublicContactMessageController::class, 'store'])
        ->name('contact-message.store');
});

// Result-checking PINs: deliberately its own top-level group, not nested under
// the "public." website group above - it must keep working for Basic-plan
// schools, which are barred from the front-facing website entirely and have
// no SchoolWebsite record for that group's middleware/layout to depend on.
Route::prefix('p/{school:portal_key}/check-result')->name('check-result.')->group(function () {
    Route::get('/', [CheckResultController::class, 'create'])->name('show');
    // The two POSTs here are open to anyone with the URL, so they carry the
    // trap field as well as their existing rate limits.
    Route::post('/identify', [CheckResultController::class, 'identify'])->middleware('honeypot')->name('identify');
    Route::get('/confirm', [CheckResultController::class, 'confirm'])->name('confirm');
    Route::post('/', [CheckResultController::class, 'verify'])->middleware('honeypot')->name('verify');
    Route::get('/result/{usage}', [CheckResultController::class, 'result'])->name('result');
    Route::get('/result/{usage}/download', [CheckResultController::class, 'download'])->name('download');
});

// ID card QR verification: also deliberately ungated and unauthenticated -
// anyone scanning a printed card must be able to reach it without logging in.
// No plan-gating needed here: cards can only ever be issued by Standard/
// Exclusive schools (id_card_access guards generation, not this route).
Route::get('id-verify/{card:uuid}', [IdCardVerificationController::class, 'show'])->name('id-verify.show');
