<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function read(DatabaseNotification $notification): RedirectResponse
    {
        abort_unless($notification->notifiable_id === auth()->id(), 403);

        $notification->markAsRead();

        return redirect($notification->data['url'] ?? route('dashboard'));
    }

    public function readAll(): RedirectResponse
    {
        auth()->user()->unreadNotifications->each->markAsRead();

        return back();
    }
}
