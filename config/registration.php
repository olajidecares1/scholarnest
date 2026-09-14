<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Incomplete Registration Reminder
    |--------------------------------------------------------------------------
    |
    | A school that creates an account and then stops, before choosing a plan
    | and submitting payment, gets one email inviting them to finish. This is
    | how long after registering that email goes out.
    |
    | Long enough that somebody who is simply still deciding is not chased,
    | short enough that they have not forgotten they started. Configurable
    | because the right answer differs between a launch push and steady state,
    | and that should not need a code change.
    |
    */
    'reminder_after_hours' => (int) env('REGISTRATION_REMINDER_AFTER_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | How Far Back To Look
    |--------------------------------------------------------------------------
    |
    | Registrations older than this are left alone. Without a floor, the first
    | run of this command after it is deployed would email every school that
    | has ever abandoned a registration, however long ago, which is a mass
    | mailing nobody asked for and the fastest way to a spam complaint.
    |
    */
    'reminder_window_days' => (int) env('REGISTRATION_REMINDER_WINDOW_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Reminders Per Run
    |--------------------------------------------------------------------------
    |
    | A ceiling on one run, so a backlog is worked through in batches rather
    | than handed to the mail provider all at once.
    |
    */
    'reminder_batch_size' => (int) env('REGISTRATION_REMINDER_BATCH_SIZE', 100),

    /*
    |--------------------------------------------------------------------------
    | Resume Link Lifetime
    |--------------------------------------------------------------------------
    |
    | How long the "continue your registration" link in that email keeps
    | working, in days.
    |
    */
    'resume_link_days' => (int) env('REGISTRATION_RESUME_LINK_DAYS', 14),
];
