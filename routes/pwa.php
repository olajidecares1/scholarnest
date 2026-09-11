<?php

use App\Http\Controllers\PwaController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Installable portals
|--------------------------------------------------------------------------
|
| The three documents a browser reads before it will offer to install a
| portal: a manifest per school per portal, an icon, and a service worker.
|
| ALL PUBLIC, deliberately. The browser fetches them before anybody signs in,
| and a manifest behind auth is a manifest that never produces an install
| prompt. They expose the school's name, logo and colour, which is what a
| visitor standing at that portal's login page can already see.
|
| REGISTERED EARLY, and before school-links.php in particular. The service
| worker sits at /sw.js in the root namespace, which is the same namespace
| /{school:slug} claims - and a Basic school called "sw.js" is not the problem
| so much as the ordering rule that would let any later root route shadow it.
| See routes/web.php for why order is behaviour here.
|
| The worker is served from the root ON PURPOSE. Its scope is then the whole
| origin, so one registration covers all four portals, and every portal on a
| school's subdomain, without four near-identical scripts. Nothing in it is
| school-specific - see the view for why it caches no page at all - so there
| is nothing for a shared scope to leak.
|
*/

Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');
Route::get('/offline', [PwaController::class, 'offline'])->name('pwa.offline');

// NO SCHOOL IN THIS ROUTE, deliberately. The icon that lands on a home screen
// is AkademicNest's, identical for every school and every portal, so it is one
// URL and one cache entry platform-wide. A school's own logo belongs in its
// portal, on its website and in the browser tab - see App\Support\Favicon.
Route::get('/pwa/icon-{size}.png', [PwaController::class, 'icon'])
    ->whereNumber('size')
    ->name('pwa.icon');

// The manifest IS per school, because that is what makes an installed app open
// one school's portal. Keyed on portal_key, like every other portal address,
// so the school is named by an opaque identifier rather than a readable slug.
Route::prefix('p/{school:portal_key}/pwa')->name('pwa.')->group(function () {
    Route::get('/{portal}/manifest.webmanifest', [PwaController::class, 'manifest'])->name('manifest');
});
