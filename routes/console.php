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
