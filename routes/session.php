<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Session keep-alive
|--------------------------------------------------------------------------
|
| Pinged by resources/js/session-keep-alive.js when a signed-in person
| actually interacts with a page, so choosing a file or drawing a signature
| is not mistaken for being away (see App\Http\Middleware\LogsOutIdleUsers).
|
| It sits behind auth for every portal guard, which is what makes the idle
| middleware treat it as a signed-in request: an active session is refreshed,
| an expired one answers 401 with where to sign in. It returns nothing and
| reveals nothing.
|
*/

Route::get('/session/keep-alive', fn () => response()->noContent())
    ->middleware('auth:web,staff,student,guardian')
    ->name('session.keep-alive');
