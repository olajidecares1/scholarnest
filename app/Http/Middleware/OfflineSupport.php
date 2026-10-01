<?php

namespace App\Http\Middleware;

use App\Support\OfflineScope;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * The server's half of using the portals with no connection.
 *
 * Three jobs, all of them invisible to a page that knows nothing about
 * offline use:
 *
 * 1. LABELLING PAGES. Every successful HTML page is labelled with whether the
 *    service worker may keep it on the device, and for whom: X-Offline-Cache
 *    is "private" (with X-Offline-Scope naming the account, see OfflineScope),
 *    "public" or "none". The worker keeps nothing that is not labelled, so a
 *    page added tomorrow is handled correctly without anybody remembering this
 *    file exists.
 *
 * 2. NEVER DOING THE SAME THING TWICE. A write made offline is held on the
 *    device with a random key (_offline_key, or the X-Offline-Key header) and
 *    sent when the connection returns, possibly more than once: a phone drops
 *    out mid-request, two tabs both see the connection come back. The first
 *    arrival is processed and remembered in offline_sync_receipts; any later
 *    arrival with the same key is answered from that receipt and never reaches
 *    the controller. The same key also protects an ordinary double-tap on a
 *    Save button online.
 *
 * 3. ANSWERING A REPLAY IN A FORM A SCRIPT CAN READ. A queued form is replayed
 *    with X-Offline-Replay. A controller answers a form with a redirect, which
 *    tells a script nothing about whether it worked, so for a replay the
 *    redirect is turned into JSON: ok, where it would have gone, and the
 *    "status" message the controller flashed.
 */
class OfflineSupport
{
    public const KEY_FIELD = '_offline_key';

    public const KEY_HEADER = 'X-Offline-Key';

    public const REPLAY_HEADER = 'X-Offline-Replay';

    /**
     * How long a write may be in flight before a second arrival with the same
     * key is assumed to be a retry of one that died, rather than a duplicate.
     */
    private const IN_FLIGHT_SECONDS = 120;

    /**
     * Pages that are public but must still never be kept on a device: they
     * carry a one-time token, a payment, or somebody's private result.
     *
     * @var list<string>
     */
    private const NEVER_KEEP = [
        'check-result*',
        'tenant.results*',
        '*result-check*',
        '*.download',
        '*download*',
        'invoices.*',
        'password.reset',
        'password.request',
        'verification.*',
        'registration.resume*',
        'id-verify*',
        'id-card.verify*',
        'subscriptions.*',
        'pwa.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe()) {
            return $this->label($request, $next($request));
        }

        $key = $this->takeKey($request);
        $replay = $request->headers->get(self::REPLAY_HEADER) === '1';

        if ($key === null) {
            $response = $next($request);

            return $replay ? $this->describe($request, $response) : $response;
        }

        $hash = hash('sha256', $key.'|'.$request->method().'|'.$request->path());

        $this->prune();

        $earlier = $this->claim($hash);

        if ($earlier !== null) {
            return $this->answerDuplicate($request, $earlier, $replay);
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->release($hash);

            throw $exception;
        }

        if ($this->succeeded($request, $response)) {
            DB::table('offline_sync_receipts')->where('key_hash', $hash)->update([
                'status' => 'done',
                'response_status' => $response->getStatusCode(),
                'redirect_to' => $response instanceof RedirectResponse ? Str::limit($this->relative($response->getTargetUrl()), 2000, '') : null,
                'message' => Str::limit((string) $this->flashedMessage($request), 480, ''),
                'updated_at' => now(),
            ]);
        } else {
            // Refused, invalid or signed out: nothing was written, so the same
            // key must be free to try again once the problem is fixed.
            $this->release($hash);
        }

        return $replay ? $this->describe($request, $response) : $response;
    }

    /**
     * Label a page with whether, and for whom, the device may keep it.
     */
    private function label(Request $request, Response $response): Response
    {
        $verdict = 'none';
        $contentType = (string) $response->headers->get('Content-Type');

        if ($response->getStatusCode() === 200 && str_contains($contentType, 'text/html')) {
            $current = OfflineScope::current($request);

            if ($current !== null) {
                $verdict = 'private';
                $response->headers->set('X-Offline-Scope', $current['scope']);

                if ($request->hasSession()) {
                    // Lets this account's session resume after a spell
                    // offline, see LogsOutIdleUsers::resumingFromOffline().
                    $request->session()->put('offline_capable_'.$current['guard'], true);
                }
            } elseif (! OfflineScope::requiresAuthentication($request)
                && ! OfflineScope::anyoneSignedIn()
                && ! $this->neverKeep($request)) {
                $verdict = 'public';
            }
        }

        $response->headers->set('X-Offline-Cache', $verdict);

        return $response;
    }

    private function neverKeep(Request $request): bool
    {
        $name = (string) optional($request->route())->getName();

        if ($name === '') {
            return true;
        }

        foreach (self::NEVER_KEEP as $pattern) {
            if (Str::is($pattern, $name)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Read the key and take it out of the input, so a controller that fills
     * a model from $request->all() never sees a field it has no column for.
     */
    private function takeKey(Request $request): ?string
    {
        $key = $request->headers->get(self::KEY_HEADER) ?: $request->input(self::KEY_FIELD);

        $request->request->remove(self::KEY_FIELD);
        $request->query->remove(self::KEY_FIELD);

        if ($request->isJson()) {
            $request->json()->remove(self::KEY_FIELD);
        }

        if (! is_string($key) || preg_match('/^[A-Za-z0-9_-]{8,80}$/', $key) !== 1) {
            return null;
        }

        return $key;
    }

    /**
     * Claim the key. Null when this request is the first to hold it; the
     * earlier receipt when somebody already did.
     */
    private function claim(string $hash): ?object
    {
        $now = now();

        $inserted = DB::table('offline_sync_receipts')->insertOrIgnore([
            'key_hash' => $hash,
            'status' => 'processing',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted > 0) {
            return null;
        }

        $receipt = DB::table('offline_sync_receipts')->where('key_hash', $hash)->first();

        if (! $receipt) {
            // Released between the insert and the read: take it again.
            return $this->claim($hash);
        }

        if ($receipt->status === 'processing'
            && now()->diffInSeconds($receipt->updated_at, absolute: true) > self::IN_FLIGHT_SECONDS) {
            // The first attempt died without finishing. Take it over.
            DB::table('offline_sync_receipts')->where('key_hash', $hash)->update(['updated_at' => $now]);

            return null;
        }

        return $receipt;
    }

    private function release(string $hash): void
    {
        DB::table('offline_sync_receipts')
            ->where('key_hash', $hash)
            ->where('status', 'processing')
            ->delete();
    }

    private function answerDuplicate(Request $request, object $receipt, bool $replay): Response
    {
        if ($receipt->status === 'processing') {
            $body = ['ok' => false, 'in_progress' => true, 'message' => 'This is already being saved.'];

            if ($replay || $request->expectsJson()) {
                return response()->json($body, 409);
            }

            return redirect()->back()->with('status', 'This is already being saved.');
        }

        $message = $receipt->message ?: 'This was already saved.';

        if ($replay || $request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'redirect' => $receipt->redirect_to,
                'message' => $message,
            ]);
        }

        // An ordinary double-tap online: go where the first one went.
        return redirect()->to($receipt->redirect_to ?: url()->previous())->with('status', $message);
    }

    /**
     * Whether the write went through, as opposed to being refused, failing
     * validation, or finding the session gone.
     */
    private function succeeded(Request $request, Response $response): bool
    {
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        if ($this->flashedErrors($request)) {
            return false;
        }

        $guards = OfflineScope::routeGuards($request);

        // A protected route answered while nobody is signed in on its guard
        // is the sign-in redirect, not the write.
        return $guards === [] || OfflineScope::current($request) !== null;
    }

    private function flashedErrors(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return in_array('errors', (array) $request->session()->get('_flash.new', []), true);
    }

    private function flashedMessage(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $new = (array) $request->session()->get('_flash.new', []);

        foreach (['status', 'success', 'warning', 'error'] as $key) {
            if (in_array($key, $new, true)) {
                $value = $request->session()->get($key);

                if (is_string($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * A replayed form, answered in JSON.
     */
    private function describe(Request $request, Response $response): Response
    {
        if ($response instanceof JsonResponse) {
            return $response;
        }

        $status = $response->getStatusCode();

        if ($this->flashedErrors($request)) {
            $bag = $request->session()->get('errors');
            $errors = $bag && method_exists($bag, 'getBag') ? $bag->getBag('default')->toArray() : [];
            $request->session()->forget('errors');

            return response()->json([
                'ok' => false,
                'message' => collect($errors)->flatten()->first() ?: 'Some details need correcting.',
                'errors' => $errors,
            ], 422);
        }

        if (OfflineScope::routeGuards($request) !== [] && OfflineScope::current($request) === null) {
            return response()->json([
                'ok' => false,
                'message' => 'Sign in again to finish saving.',
                'redirect' => $response instanceof RedirectResponse ? $this->relative($response->getTargetUrl()) : null,
            ], 401);
        }

        if ($status >= 400) {
            return response()->json([
                'ok' => false,
                'message' => $this->flashedMessage($request) ?: 'The server could not accept this ('.$status.').',
            ], $status);
        }

        return response()->json([
            'ok' => true,
            'redirect' => $response instanceof RedirectResponse ? $this->relative($response->getTargetUrl()) : null,
            'message' => $this->flashedMessage($request),
        ]);
    }

    private function relative(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $query ? "{$path}?{$query}" : $path;
    }

    /**
     * Keep the receipts table small. A queued write older than a fortnight is
     * not coming back.
     */
    private function prune(): void
    {
        if (random_int(1, 200) !== 1) {
            return;
        }

        DB::table('offline_sync_receipts')->where('created_at', '<', now()->subDays(14))->delete();
    }
}
