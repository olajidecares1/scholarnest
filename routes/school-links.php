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
*/

require __DIR__.'/auth.php';

/*
|--------------------------------------------------------------------------
| Basic-plan school landing - MUST BE THE LAST ROUTE IN THE APPLICATION
|--------------------------------------------------------------------------
|
| edunest.com/greenfield-college
|
| Where the Basic-plan school finder above sends people. A Basic school has no
| public website, so this is its portal entry point rather than a home page.
|
| This route occupies the root namespace, so a school slug competes with every
| top-level path the application owns. Three things keep that safe:
|
|   1. It is registered LAST - after every route in this file AND after the
|      ones in auth.php, which is required above. Laravel matches the first
|      route that fits, so a real route always wins. Nothing may be registered
|      after this block.
|
|   2. The pattern excludes reserved words outright (config
|      basic_portal.reserved_slugs), so /login and /dashboard can never reach
|      this controller even if the ordering above were ever disturbed.
|
|   3. The same reserved list is enforced when a school picks its slug, so the
|      collision cannot be created in the first place.
|
| The slug pattern also requires lowercase letters, digits and hyphens only,
| which is exactly what Str::slug() produces - so the obfuscated hex URIs used
| elsewhere in this file cannot be mistaken for a school.
|
*/

/*
|--------------------------------------------------------------------------
| A school's own result-checking address
|--------------------------------------------------------------------------
|
| edunest.com/greenfield-college/result
|
| The link a school hands to parents. It carries no secret of its own - a
| result token is still required to see anything - but it does decide WHICH
| school's tokens are even considered, and that decision is made here from the
| address rather than from anything the visitor can type.
|
| Registered immediately before the single-segment landing route below, and
| for the same reasons: it sits in the root namespace, so it uses the same
| reserved-word exclusion and the same lowercase-slug pattern. The trailing
| "/result" segment means it cannot collide with the landing route itself.
|
| The parameter is the school's result_link_slug, NOT its slug - a school can
| retire a link that has spread too far without renaming itself.
|
*/
Route::prefix('{school:result_link_slug}/result')->name('school-result.')->group(function () {
    Route::get('/', [CheckResultController::class, 'create'])->name('show');
    Route::post('/identify', [CheckResultController::class, 'identify'])->name('identify');
    Route::get('/confirm', [CheckResultController::class, 'confirm'])->name('confirm');
    Route::post('/', [CheckResultController::class, 'verify'])->name('verify');
    Route::get('/view/{usage}', [CheckResultController::class, 'result'])->name('result');
    Route::get('/view/{usage}/download', [CheckResultController::class, 'download'])->name('download');
})->where('school', sprintf(
    '(?!(?:%s)$)[a-z0-9]+(?:-[a-z0-9]+)*',
    implode('|', array_map(
        static fn (string $slug): string => preg_quote($slug, '/'),
        config('basic_portal.reserved_slugs'),
    )),
));
Route::get('/{school:slug}', [BasicSchoolLandingController::class, 'show'])
    ->where('school', sprintf(
        // The alternation is wrapped in its own group before the "$" so that
        // the anchor applies to EVERY reserved word rather than only the last
        // one. Without the group, "(?!news|...|edunest$)" rejects any slug
        // merely STARTING with a reserved word, which would quietly 404 a
        // legitimate school called "Newspaper College".
        '(?!(?:%s)$)[a-z0-9]+(?:-[a-z0-9]+)*',
        implode('|', array_map(
            static fn (string $slug): string => preg_quote($slug, '/'),
            config('basic_portal.reserved_slugs'),
        )),
    ))
    ->name('basic-portal.school');
