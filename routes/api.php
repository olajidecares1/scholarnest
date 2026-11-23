<?php

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
|
| A manifest, like routes/web.php. Each version is its own file, and adding
| v2 means adding a file and a line here, not editing v1 in place, which is
| the thing versioning exists to prevent.
|
| Everything is under /api (the prefix is set in bootstrap/app.php) and then
| under a version, so the first path segment a client ever writes is one it
| can keep writing.
|
*/

use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(base_path('routes/api/v1.php'));

// WhatsApp delivery receipts from Meta. Signed with the app secret and
// checked on every call; see WhatsAppWebhookController.
Route::get('webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify'])
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:60,1')
    ->name('webhooks.whatsapp.verify');

// Not under the API's 60-a-minute limit: Meta sends a receipt per message
// (sent, delivered, read), so a bulk send arrives in bursts.
Route::post('webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive'])
    ->withoutMiddleware('throttle:api')
    ->middleware('throttle:1200,1')
    ->name('webhooks.whatsapp');
