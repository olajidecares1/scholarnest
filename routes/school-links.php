<?php

use App\Http\Controllers\CheckResultController;
use App\Http\Controllers\Portal\Basic\SchoolLandingController as BasicSchoolLandingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| School addresses at the site root
|--------------------------------------------------------------------------
|
| Loaded LAST, and it has to be. Both routes here live in the root
| namespace and would shadow anything registered after them - see the
| notes on each.
|
| Neither names its school any more. Both carry an opaque fixed-length key,
| which is also what retired the reserved-word patterns this file used to
| need: no reserved path is 16 or 20 mixed-case alphanumerics.
|
*/

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| A school's own result-checking address
|--------------------------------------------------------------------------
|
| akademicanest.com/hT4wLpZs3H8Kq2mV/result
|
| The link a school hands to parents. It carries no secret of its own - a
| result token is still required to see anything - but it does decide WHICH
| school's tokens are even considered, and that decision is made here from the
| address rather than from anything the visitor can type.
|
| The parameter is the school's result_link_slug, NOT its slug or its
| portal_key: a school can burn a link that has spread too far and get another
| without changing anything else about itself.
|
| It used to be built from the school's name and read /greenfield-college/
| result, which put that name into every message, notice board and referrer
| header the link reached. It is random now. Nothing is lost - a parent clicks
| this link rather than typing it - and the file below no longer needs the
| reserved-word machinery a name-shaped slug required.
|
*/
Route::prefix('{school:result_link_slug}/result')->name('school-result.')->group(function () {
    Route::get('/', [CheckResultController::class, 'create'])->name('show');
    Route::post('/identify', [CheckResultController::class, 'identify'])->name('identify');
    Route::get('/confirm', [CheckResultController::class, 'confirm'])->name('confirm');
    Route::post('/', [CheckResultController::class, 'verify'])->name('verify');
    Route::get('/view/{usage}', [CheckResultController::class, 'result'])->name('result');
    Route::get('/view/{usage}/download', [CheckResultController::class, 'download'])->name('download');
    // A FIXED-LENGTH MIXED-CASE KEY, not a slug built from the school's name.
    //
    // This is the link a school hands to parents, so it travels: into messages,
    // bookmarks, browser history and referrer headers. It used to read
    // /greenfield-college/result, which named the school in all of them.
    //
    // The reserved-word exclusion the slug pattern needed is gone with it, and
    // deliberately: no reserved path is 16 mixed-case alphanumerics, so the
    // pattern cannot match one. It also cannot be mistaken for the obfuscated
    // admin paths, which are 128 hex characters.
})->where('school', '[A-Za-z0-9]{16}');

// The Basic-plan school landing, on the same opaque key as everything else.
//
// It used to be akademicanest.com/greenfield-college - a single readable segment at
// the root, which is why this file carried so much machinery to stop a school
// slug colliding with a reserved path. The key retires all of it: 20 mixed-case
// alphanumerics cannot be "login" or "dashboard" or "legal", so there is no
// reserved list to keep in step and no "Newspaper College" edge case left.
//
// Still the LAST route in the application. The pattern makes a collision
// impossible rather than unlikely, but the ordering costs nothing and the next
// person to add a root-level route should still find this at the bottom.
Route::get('/{school:portal_key}', [BasicSchoolLandingController::class, 'show'])
    ->where('school', '[A-Za-z0-9]{20}')
    ->name('basic-portal.school');
