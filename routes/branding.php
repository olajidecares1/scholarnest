<?php

use App\Http\Controllers\BrandingImageController;
use App\Http\Controllers\StoredFileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform branding images
|--------------------------------------------------------------------------
|
| The uploaded logo and favicon, served out of the database. See
| App\Models\BrandingImage for why they are not served from /storage.
|
| PUBLIC: a favicon is requested before anybody signs in.
|
| The name is constrained to one path segment of safe characters, no slash,
| so nothing outside branding/ can be named, and registered before
| school-links.php claims the root namespace. See routes/web.php.
|
*/

Route::get('/branding/{file}', [BrandingImageController::class, 'show'])
    ->where('file', '[A-Za-z0-9_-]+\.[A-Za-z0-9]+')
    ->name('branding.show');

// Public-disk uploads kept in the database, when no bucket is attached, see
// App\Support\Storage\DatabaseStorageFallback. Letters, digits, dot, dash,
// underscore and slash only; stored names are generated, never the uploader's.
Route::get('/files/{path}', [StoredFileController::class, 'show'])
    ->where('path', '[A-Za-z0-9_\-]+(/[A-Za-z0-9_\-]+)*\.[A-Za-z0-9]+')
    ->name('stored-files.show');
