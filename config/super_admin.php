<?php

/*
|--------------------------------------------------------------------------
| Super Admin access
|--------------------------------------------------------------------------
|
| The Super Admin sign-in is not advertised anywhere in the public interface.
| It is revealed by clicking the AkademicNest logo on the registration page a set
| number of times in quick succession.
|
| This is obscurity, and obscurity is not security. It keeps the door out of
| sight of people idly looking around; it does nothing against anyone who
| finds it. Everything that actually protects the account, credential
| checking, the Super Admin role requirement, the account-status check, both
| rate limiters, CSRF and session handling, lives on the server and applies
| identically whether the form was reached by the click sequence or by
| someone posting straight at the endpoint.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Reveal sequence
    |--------------------------------------------------------------------------
    |
    | How many clicks on the logo reveal the sign-in dialog.
    |
    */

    'reveal_clicks' => env('SUPER_ADMIN_REVEAL_CLICKS', 5),

    /*
    |--------------------------------------------------------------------------
    | Reveal timeout
    |--------------------------------------------------------------------------
    |
    | Milliseconds allowed between one click and the next. Pause for longer
    | than this and the counter starts again from zero, so the sequence has to
    | be deliberate rather than something a visitor stumbles into over the
    | course of a browsing session.
    |
    */

    'reveal_click_timeout' => env('SUPER_ADMIN_REVEAL_CLICK_TIMEOUT', 2000),

];
