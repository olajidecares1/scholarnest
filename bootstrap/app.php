<?php

use App\Exceptions\FeatureRequiresUpgrade;
use App\Exceptions\SchoolWebsiteNotPublished;
use App\Http\Middleware\Api\EnsureApiAccountIsActive;
use App\Http\Middleware\Api\EnsureApiActorIs;
use App\Http\Middleware\CheckMaintenanceMode;
use App\Http\Middleware\EnforceTenantHostBoundary;
use App\Http\Middleware\EnsureGuardianIsActive;
use App\Http\Middleware\EnsureHasPermission;
use App\Http\Middleware\EnsurePasswordHasBeenChanged;
use App\Http\Middleware\EnsureSchoolHasFeature;
use App\Http\Middleware\EnsureSchoolHasPortalAccess;
use App\Http\Middleware\EnsureSchoolHasResultPinAccess;
use App\Http\Middleware\EnsureSchoolIsActivated;
use App\Http\Middleware\EnsureStaffIsActive;
use App\Http\Middleware\EnsureStaffIsTeacher;
use App\Http\Middleware\EnsureStudentIsActive;
use App\Http\Middleware\EnsureUserIsSchoolAdmin;
use App\Http\Middleware\EnsureUserIsSuperAdmin;
use App\Http\Middleware\LogsOutIdleUsers;
use App\Http\Middleware\RedirectToCanonicalHost;
use App\Http\Middleware\RedirectToCustomDomain;
use App\Http\Middleware\RedirectToHttps;
use App\Http\Middleware\RejectBotSubmissions;
use App\Http\Middleware\ResolveTenantFromCustomDomain;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\TrackPageView;
use App\Http\Middleware\ValidateBasicPortalToken;
use App\Http\Middleware\ValidateSchoolPortalToken;
use App\Models\School;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',

        // Versioned, and prefixed once here rather than on every route file.
        // See routes/api.php.
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // TLS ends at the load balancer, not at PHP. Until the proxy is
        // trusted Laravel ignores X-Forwarded-Proto, every request looks
        // insecure, and RedirectToHttps answers each https request with a
        // 301 to itself until the browser gives up. See the commit message.
        $middleware->trustProxies(at: '*');

        // One call, because Middleware::alias() ASSIGNS rather than merges, a
        // second call anywhere in this closure silently discards every alias
        // above it, and the first thing you see is "Target class
        // [school_admin] does not exist" from an unrelated route.
        $middleware->alias([
            'super_admin' => EnsureUserIsSuperAdmin::class,

            // The trap field on the forms strangers can reach. An alias rather
            // than a global: it belongs on public forms, not on every
            // authenticated POST in the application.
            'honeypot' => RejectBotSubmissions::class,

            'school_admin' => EnsureUserIsSchoolAdmin::class,
            'school_activated' => EnsureSchoolIsActivated::class,
            'permission' => EnsureHasPermission::class,
            'student_active' => EnsureStudentIsActive::class,
            'guardian_active' => EnsureGuardianIsActive::class,
            'staff_active' => EnsureStaffIsActive::class,

            // The API's two gates. api.active re-checks on every request what
            // a session portal only checks at sign-in, because a token lives
            // for weeks; api.actor takes the role as a parameter
            // (api.actor:student), since a valid token is valid everywhere
            // until something says which endpoints it belongs at.
            'api.active' => EnsureApiAccountIsActive::class,
            'api.actor' => EnsureApiActorIs::class,

            // Takes the guard name as a parameter: password_changed:student.
            // Forces a Student, Staff or Guardian whose password was reset by
            // their School Admin to choose their own before they can use the
            // portal for anything else.
            'password_changed' => EnsurePasswordHasBeenChanged::class,

            'staff_is_teacher' => EnsureStaffIsTeacher::class,
            'portal_access' => EnsureSchoolHasPortalAccess::class,
            'result_pin_access' => EnsureSchoolHasResultPinAccess::class,

            // Every plan restriction, by feature: plan_feature:cbt,
            // plan_feature:events, and so on. This replaced four separate
            // middleware classes that each held a copy of the same rule and
            // refused in their own words, which is how the project ended up
            // with some premium features gated and others not.
            'plan_feature' => EnsureSchoolHasFeature::class,
            'resolve_tenant_domain' => ResolveTenantFromCustomDomain::class,
            'redirect_to_custom_domain' => RedirectToCustomDomain::class,
            'portal_token' => ValidateSchoolPortalToken::class,

            // The 32-character token gating the Basic-plan portal entry point.
            'basic_portal_token' => ValidateBasicPortalToken::class,
            'auth.session' => AuthenticateSession::class,
        ]);

        // Applied to every response the application makes, web and API alike:
        // a browser cannot enforce a policy it was never sent, and an API
        // error page is as capable of being framed or sniffed as any other.
        // RedirectToHttps runs first so a plaintext request is turned away
        // before anything else looks at it.
        $middleware->prepend([
            // TrustProxies first, and that order is the whole point: prepends
            // land AHEAD of the default global stack, Laravel's own TrustProxies
            // included. Without this line RedirectToHttps reads the scheme before
            // X-Forwarded-Proto is trusted, sees plain http behind the load
            // balancer, and redirects https to itself until the browser gives up.
            // array_unique in getGlobalMiddleware keeps this copy and drops the
            // default one, so it still runs exactly once.
            TrustProxies::class,

            RedirectToHttps::class,

            // After the scheme is settled and before anything reads the
            // session: a request being sent to the canonical host must not
            // start a session on the host it is leaving.
            RedirectToCanonicalHost::class,

            SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            // After StartSession so it can see who is signed in, and (by the
            // priority entry below) before auth, so a platform page asked for
            // on a school's host goes straight to the platform instead of via
            // a sign-in page on the school's host first. See the class.
            EnforceTenantHostBoundary::class,
            CheckMaintenanceMode::class,
            TrackPageView::class,
            LogsOutIdleUsers::class,
        ]);

        // The tenant host check runs before authentication. See the comment on
        // its web() entry above.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: EnforceTenantHostBoundary::class,
        );

        // Laravel 11 stopped throttling the api group by default. An API
        // without a limiter is an invitation to page through a school's
        // records as fast as the network allows, so it goes back on here;
        // the limits themselves are in AppServiceProvider.
        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * A stale form should not be a dead end.
         *
         * Laravel answers an expired CSRF token with a bare "419 Page Expired"
         * screen that has no way back to what you were doing. It is easy to
         * reach without doing anything wrong: leaving a login page open past
         * the session lifetime, submitting from a tab restored after a
         * restart, or returning to a page cached from an earlier session.
         *
         * The honest response is to send the form back with an explanation,
         * so the fix is to type the password again rather than to work out
         * what "419" means. Nothing is loosened by this, the request is still
         * rejected and never reaches the controller.
         */
        /*
         * Matched on the 419 STATUS rather than on TokenMismatchException.
         *
         * Laravel's handler calls prepareException() before it runs these
         * callbacks, and that turns a TokenMismatchException into a plain
         * HttpException(419), so a callback typed against the original class
         * is never reached. The token mismatch survives as the previous
         * exception, which is what makes this specific rather than a blanket
         * rule for every 419.
         */
        /*
         * A plan restriction is a 403, but it is not an error.
         *
         * abort(403, 'CBT requires the Standard or Exclusive plan.') rendered
         * that sentence on an otherwise empty page with no links on it, a
         * paying customer told they had done something wrong and then left
         * there. The status stays 403, because the refusal is real and
         * anything reading the code should see one; what changes is that the
         * response names the feature, says which plan includes it, and always
         * offers the way back to the dashboard.
         */
        $exceptions->render(function (FeatureRequiresUpgrade $exception, Request $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $exception->getMessage(),
                    'feature' => $exception->feature->value,
                    'required_plans' => array_map(fn ($plan) => $plan->value, $exception->feature->requiredPlans()),
                ], 403);
            }

            $school = $request->user()?->school ?? auth('staff')->user()?->school;

            return response()->view('errors.plan-restricted', [
                'feature' => $exception->feature,
                'currentPlanName' => $school?->activeSubscription?->plan?->name ?? 'Basic Plan',
            ], 403);
        });

        /*
         * A school's address, before it has published a website.
         *
         * Registration hands a school its subdomain at once; building the
         * website is something somebody sits down with later. Between the
         * two, greenfield.akademicanest.com answered a bare "404 NOT FOUND",
         * so the first thing a new school saw at its own address was the
         * platform apparently broken, with nothing on the page to say the
         * address was right or that their portal was already live at it.
         *
         * 200 for the HTML, because this is not an error page standing in for
         * something that was asked for: it is a real page, at a real address,
         * carrying the school's name and a working way into their portal. It
         * declares noindex, so an unfinished site is not indexed as the
         * school's web presence. Anything reading a status code still gets
         * the 404 the exception carries.
         */
        $exceptions->render(function (SchoolWebsiteNotPublished $exception, Request $request) {
            $school = $exception->school;

            if ($request->expectsJson()) {
                return response()->json(['message' => $exception->getMessage()], 404);
            }

            // On the school's own host the portal is simply /portal. Reached
            // by the default path instead, it is the /p/{portal_key}/ one.
            $onOwnHost = $request->attributes->get('tenant') instanceof School;
            $host = $request->getHost();

            return response()->view('public.school-website-unpublished', [
                'school' => $school,
                'portalUrl' => $onOwnHost
                    ? route('tenant.portal.index', ['tenantDomain' => $host], absolute: false)
                    : route('portal.index', $school, absolute: false),
                'resultsUrl' => $onOwnHost
                    ? route('tenant.results.show', ['tenantDomain' => $host], absolute: false)
                    : $school->resultLinkUrl(),
            ], 200);
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request) {
            if ($exception->getStatusCode() !== 419) {
                return null;
            }

            $message = 'Your session expired while this page was open. Please try again.';

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 419);
            }

            return redirect()->back()
                // Never the password, and never the dead token itself.
                ->withInput($request->except(['password', 'password_confirmation', '_token']))
                // Both keys, because sign-in forms across the app name the
                // field either "login" or "email"; whichever this form uses
                // renders the message beside it.
                ->withErrors(['login' => $message, 'email' => $message]);
        });
    })->create();
