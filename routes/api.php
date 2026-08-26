<?php

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| A manifest, like routes/web.php. Each version is its own file, and adding
| v2 means adding a file and a line here - not editing v1 in place, which is
| the thing versioning exists to prevent.
|
| Everything is under /api (the prefix is set in bootstrap/app.php) and then
| under a version, so the first path segment a client ever writes is one it
| can keep writing.
|
*/

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(base_path('routes/api/v1.php'));
