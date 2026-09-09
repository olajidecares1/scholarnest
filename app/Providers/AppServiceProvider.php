<?php

namespace App\Providers;

use App\Services\DocumentExtraction\LocalQuestionExtractor;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use App\Services\QueueWorkerHealth;
use App\Support\PortalLoginRedirect;
use App\Support\ProductionConfiguration;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Document extraction runs locally, with no key, no credit and no
        // network. The binding is here rather than type-hinted directly so a
        // different engine - OCR for scanned pages, or a hosted model - can be
        // swapped in later by changing one line, without the local extractor
        // ever ceasing to be the default that works on its own.
        $this->app->bind(QuestionExtractionProvider::class, LocalQuestionExtractor::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Refuses to serve a request from a production environment configured
        // to leak - APP_DEBUG on, or a session cookie that is not secure and
        // encrypted. Outside production, and for console commands, it does
        // nothing. See App\Support\ProductionConfiguration.
        ProductionConfiguration::verify(
            $this->app->environment('production'),
            $this->app->runningInConsole(),
        );

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        /*
         * An expired session sends people to their own portal's login.
         *
         * Laravel's default is route('login'), and in this application that
         * name redirects on to route('register') - the shared sign-in page was
         * removed once every portal got its own, and the name was left aimed at
         * the public front door. The result was that stepping away from the
         * dashboard for four minutes ended on a form inviting a School Admin to
         * register the school they already run.
         *
         * Fixed here, at the one place every guest redirect passes through,
         * rather than by pointing route('login') somewhere else - that name is
         * still the registration page's front door and several other things
         * lean on it. See App\Support\PortalLoginRedirect.
         */
        Authenticate::redirectUsing(fn (Request $request) => PortalLoginRedirect::for($request));

        /*
         * Which school and portal this browser last signed in to.
         *
         * The School Admin dashboard is an obfuscated path with no school in
         * it, reachable on the default host, so once the session is gone there
         * is otherwise nothing left to say which school the person belongs to -
         * and "your session expired" would degrade to the generic sign-in.
         * Listening on the Login event covers all four portals at once rather
         * than adding the same line to each controller's store().
         */
        Event::listen(function (Login $event): void {
            $school = $event->user->school ?? null;

            if ($school) {
                Cookie::queue(Cookie::make(
                    PortalLoginRedirect::COOKIE,
                    "{$school->id}:{$event->guard}",
                    PortalLoginRedirect::COOKIE_MINUTES,
                    httpOnly: true,
                ));
            }
        });

        /*
         * A queue worker stamps a heartbeat on every pass of its loop - idle
         * or busy, roughly once a second.
         *
         * Without it, "is anything processing jobs?" could only be inferred
         * from a job having sat unclaimed for a long time, which meant someone
         * who uploaded a CBT document with no worker running watched a
         * progress bar for over two minutes before being told the truth. With
         * it, the answer is known within one poll. See QueueWorkerHealth.
         */
        Queue::looping(function (): void {
            QueueWorkerHealth::heartbeat();
        });

        /*
         * LogSuccessfulLogin and LogFailedLogin are NOT registered here.
         *
         * Laravel discovers listeners in app/Listeners by the type hint on
         * their handle() method, so these two were registered twice - once by
         * discovery and once by hand - and every login wrote its audit entry
         * twice. `php artisan event:list` showed the pair plainly:
         *
         *     Illuminate\Auth\Events\Login
         *       ⇂ App\Listeners\LogSuccessfulLogin
         *       ⇂ App\Listeners\LogSuccessfulLogin@handle
         *
         * A school's User carries the school's name, and registering signs the
         * new admin straight in, so one registration put "School ABC logged
         * in." in front of the AkademicNest Team twice. LogPasswordReset was never
         * listed here and appeared exactly once, which is what the other two
         * now do.
         */

        // Applies everywhere Password::defaults() is used as a validation
        // rule (registration, self-service password change, forgot-password
        // reset, Super Admin user creation) - a single source of truth for
        // the app-wide minimum: 8+ characters, upper+lower case, a number,
        // and a symbol.
        Password::defaults(fn () => Password::min(8)->letters()->mixedCase()->numbers()->symbols());

        /*
         * What "throttle:api" means.
         *
         * Keyed on the TOKEN rather than the account, so a parent with the app
         * on a phone and a tablet gets a budget for each - and losing a device
         * to a runaway retry loop does not lock them out of the other one.
         * Falling back to the account id keeps the limit meaningful if a token
         * is ever absent, and to the IP for anything unauthenticated.
         *
         * 60 a minute is generous for drawing screens and mean for walking a
         * school's records. Sign-in is throttled separately and much harder,
         * on the route itself.
         */
        RateLimiter::for('api', function (Request $request) {
            $token = $request->user()?->currentAccessToken();

            $key = $token?->getKey()
                ? 'token:'.$token->getKey()
                : ($request->user()?->getKey() ? 'account:'.$request->user()->getKey() : 'ip:'.$request->ip());

            return Limit::perMinute(60)->by($key);
        });
    }
}
