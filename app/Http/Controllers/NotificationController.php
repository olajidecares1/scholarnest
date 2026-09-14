<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\URL;

class NotificationController extends Controller
{
    /**
     * Open the thing a notification is about, and mark it read on the way.
     *
     * THIS USED TO THROW PEOPLE OUT OF WHERE THEY WERE. It redirected to
     * `route('dashboard')` whenever a notification carried no url of its own,
     * and twelve of the application's twenty-two notifications carry none, so
     * clicking most of them abandoned whatever page you were on and sent you
     * to a generic dashboard. For an account whose school had no active
     * subscription, or whose school had been deleted, that dashboard bounces
     * again, and the second bounce lands on the sign-in page, which is what
     * "clicking a notification sends me to the registration page" was.
     *
     * The fallback is now back(), so a notification without a destination
     * marks itself read and leaves you exactly where you were, inside your own
     * portal. Nothing navigates anywhere it was not asked to.
     */
    public function read(DatabaseNotification $notification): RedirectResponse
    {
        // BOTH halves of the morph, not just the id. Comparing the id alone
        // let a User with id 7 open a Staff member's notification with id 7,
        // different tables, same number, and the notifiable_type is the only
        // thing that tells them apart.
        abort_unless(
            $notification->notifiable_id === auth()->id()
            && $notification->notifiable_type === auth()->user()?->getMorphClass(),
            403,
        );

        $notification->markAsRead();

        return redirect($this->destinationFor($notification));
    }

    public function readAll(): RedirectResponse
    {
        auth()->user()->unreadNotifications->each->markAsRead();

        return back();
    }

    /**
     * Where this notification says to go, or back where the reader was.
     *
     * The url is read out of the database, so it is treated as untrusted:
     * only a URL on this application's own host is followed. A notification
     * row that somehow held an external address would otherwise be an open
     * redirect from inside an authenticated session.
     */
    private function destinationFor(DatabaseNotification $notification): string
    {
        $url = $notification->data['url'] ?? null;

        if (is_string($url) && $url !== '' && $this->isOwnHost($url)) {
            return $url;
        }

        return url()->previous();
    }

    private function isOwnHost(string $url): bool
    {
        // A relative path has no host to disagree with, so it is ours.
        if (! str_contains($url, '://')) {
            return str_starts_with($url, '/');
        }

        $host = parse_url($url, PHP_URL_HOST);

        return $host !== null && ($host === parse_url(URL::to('/'), PHP_URL_HOST) || str_ends_with($host, '.'.parse_url(URL::to('/'), PHP_URL_HOST)));
    }
}
