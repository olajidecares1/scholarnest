<?php

namespace App\Providers;

use App\Listeners\LogFailedLogin;
use App\Listeners\LogSuccessfulLogin;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
    }
}
