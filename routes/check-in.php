<?php

use App\Http\Controllers\CheckInController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| QR check-in
|--------------------------------------------------------------------------
|
| The poster by the gate, and the two requests the page behind it makes.
|
| NO GUARD ON THE GROUP, deliberately. One poster is scanned by pupils and by
| staff, who sign in on two different guards, and a route that picked one
| would turn the other away at the gate. The controller reads whichever guard
| this phone is signed in on and refuses politely if neither.
|
| REGISTERED BEFORE school-links.php, like everything else that is not a
| school slug, see routes/web.php for why the order is behaviour here.
|
| The address carries the school's portal key and the poster's token, and no
| name: the paper on the wall is public and stays public, but it should not
| also announce which school it belongs to to anyone who photographs it.
|
*/

Route::prefix('p/{school:portal_key}/check-in/{token}')->name('check-in.')->group(function () {
    Route::get('/', [CheckInController::class, 'show'])->name('show');
    Route::get('whoami', [CheckInController::class, 'whoami'])->name('whoami');

    // Throttled: this is an unauthenticated-reachable POST, and a school with
    // 2,000 pupils arriving inside the same twenty minutes is the normal load,
    // so the limit is generous per device rather than tight.
    Route::post('/', [CheckInController::class, 'store'])
        ->middleware('throttle:60,1')
        ->name('store');
});
