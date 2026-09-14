<?php

use App\Exceptions\FeatureRequiresUpgrade;
use App\Http\Middleware\Api\EnsureApiAccountIsActive;
use App\Http\Middleware\Api\EnsureApiActorIs;
use App\Http\Middleware\CheckMaintenanceMode;
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
            CheckMaintenanceMode::class,
            TrackPageView::class,
            LogsOutIdleUsers::class,
        ]);

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
