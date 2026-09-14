<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Interval is configuration-driven (custom_domain.auto_verify_interval_minutes)
// rather than a fixed ->everyFiveMinutes(), so it can be tuned per environment
// without a code change.
Schedule::command('custom-domains:auto-verify')
    ->cron('*/'.config('custom_domain.auto_verify_interval_minutes').' * * * *');

// Hourly, though a school is only ever reminded once, the command decides
// who is due from registration.reminder_after_hours. Checking often and
// sending rarely means a school registering at 4pm is reminded at about 4pm
// the next day rather than whenever a daily job happens to run.
//
// withoutOverlapping because a large backlog can outlast the hour, and two
// copies of this racing each other is how a school gets two emails.
Schedule::command('registrations:remind-incomplete')
    ->hourly()
    ->withoutOverlapping();
