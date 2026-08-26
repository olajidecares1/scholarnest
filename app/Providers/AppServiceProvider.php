<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use App\Services\DocumentExtraction\LocalQuestionExtractor;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use App\Support\ProductionConfiguration;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
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

        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Failed::class, LogFailedLogin::class);

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
