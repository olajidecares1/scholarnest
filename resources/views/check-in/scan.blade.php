{{--
    The page behind the QR poster.

    SELF-CONTAINED, like the offline page next door and for the same reason:
    the service worker keeps a copy so that scanning at a gate with no signal
    still opens something. Anything this referenced, a stylesheet, a font, a
    logo, would have to be cached too or it would render as unstyled text on
    the one morning it matters.

    IT NAMES NOBODY. The school, yes, which is printed on the poster anyway.
    The person, never: this file is the one page in the application the worker
    is allowed to keep, and that permission only holds while there is nothing
    in it that the next person to pick up the phone should not read. Who is
    signed in is fetched separately, at runtime, and never cached.
--}}
@php
    // Relative, not absolute: the page is the same document whether it was
    // reached on the school's own domain or the central one, and a cached copy
    // must not carry yesterday's host into today's request.
    $storeUrl = route('check-in.store', ['school' => $school, 'token' => $token], absolute: false);
    $whoamiUrl = route('check-in.whoami', ['school' => $school, 'token' => $token], absolute: false);
@endphp
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title>Check in</title>
        <style>
            :root { color-scheme: light dark; }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                background: #f8fafc;
                color: #0f172a;
            }
            @media (prefers-color-scheme: dark) {
                body { background: #0f172a; color: #e2e8f0; }
                .card { background: #1e293b !important; border-color: #334155 !important; }
                .muted { color: #94a3b8 !important; }
                .school { color: #cbd5f5 !important; }
            }
            .card {
                width: 100%;
                max-width: 380px;
                text-align: center;
                background: #ffffff;
                border: 1px solid #e2e8f0;
                border-radius: 16px;
                padding: 30px 24px;
            }
            .school { font-size: 12px; letter-spacing: .14em; text-transform: uppercase; color: #475569; margin: 0 0 18px; font-weight: 700; }
            .mark { width: 64px; height: 64px; margin: 0 auto 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 30px; background: rgba(79, 70, 229, .12); }
            .mark.good { background: rgba(22, 163, 74, .14); }
            .mark.bad { background: rgba(220, 38, 38, .14); }
            .mark.wait { background: rgba(217, 119, 6, .14); }
            h1 { margin: 0 0 6px; font-size: 20px; font-weight: 800; }
            p { margin: 0; font-size: 14px; line-height: 1.55; }
            .muted { color: #64748b; margin-top: 8px; font-size: 13px; }
            .time { font-variant-numeric: tabular-nums; font-weight: 700; font-size: 26px; margin: 10px 0 0; }
            .actions { margin-top: 22px; display: flex; flex-direction: column; gap: 10px; }
            a.button, button {
                appearance: none;
                border: 0;
                border-radius: 10px;
                padding: 12px 16px;
                font-size: 14px;
                font-weight: 700;
                cursor: pointer;
                text-decoration: none;
                display: block;
                background: #4f46e5;
                color: #ffffff;
            }
            button.secondary { background: transparent; color: #4f46e5; border: 1px solid #c7d2fe; }
            [hidden] { display: none !important; }
        </style>
    </head>
    <body>
        <main class="card">
            <p class="school">{{ $school->name }}</p>

            <div class="mark wait" id="mark" aria-hidden="true">&hellip;</div>
            <h1 id="headline">Checking you in</h1>
            <p id="detail">Hold still while your phone finds where you are.</p>
            <p class="time" id="time" hidden></p>
            <p class="muted" id="who" hidden></p>

            <div class="actions">
                <a class="button" id="sign-in" href="#" hidden>Sign in first</a>
                <button type="button" class="secondary" id="again" hidden>Scan again</button>
            </div>
        </main>

        <script>
            (function () {
                'use strict';

                const ENDPOINT = @json($storeUrl);
                const WHOAMI = @json($whoamiUrl);

                const mark = document.getElementById('mark');
                const headline = document.getElementById('headline');
                const detail = document.getElementById('detail');
                const time = document.getElementById('time');
                const who = document.getElementById('who');
                const signIn = document.getElementById('sign-in');
                const again = document.getElementById('again');

                function paint(state, title, text, stamp) {
                    mark.className = 'mark ' + state;
                    mark.textContent = state === 'good' ? '✓' : state === 'bad' ? '✕' : '…';
                    headline.textContent = title;
                    detail.textContent = text;
                    time.hidden = !stamp;
                    time.textContent = stamp || '';
                    again.hidden = false;
                }

                // The phone's own position. Refused, unavailable or simply slow
                // all come back the same way, as no position, because the
                // server treats all three alike: a scan it cannot place is a
                // scan it cannot count.
                function locate() {
                    return new Promise(function (resolve) {
                        if (!navigator.geolocation) {
                            resolve(null);
                            return;
                        }

                        navigator.geolocation.getCurrentPosition(
                            function (position) { resolve(position); },
                            function () { resolve(null); },
                            { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 },
                        );
                    });
                }

                // Hand the scan to the service worker, which owns the queue and
                // the retrying. Without one, which is every first visit, post it
                // here instead; there is no offline to survive on a page that
                // just came off the network.
                function deliver(scan) {
                    const worker = navigator.serviceWorker && navigator.serviceWorker.controller;

                    if (worker) {
                        return new Promise(function (resolve) {
                            const channel = new MessageChannel();
                            channel.port1.onmessage = function (event) { resolve(event.data); };
                            worker.postMessage({ type: 'check-in', endpoint: ENDPOINT, whoami: WHOAMI, scan: scan }, [channel.port2]);
                        });
                    }

                    // No worker yet, which is every first visit. There is no
                    // offline to survive on a page that just came off the
                    // network, so post it straight through. The token comes
                    // from whoami rather than from this HTML, which may have
                    // been served from a cache older than the session.
                    return fetch(WHOAMI, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (response) { return response.json(); })
                        .then(function (body) {
                            return fetch(ENDPOINT, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': body.csrf_token || '',
                                },
                                body: JSON.stringify({ scans: [scan] }),
                            });
                        })
                        .then(function (response) {
                            if (response.status === 401) {
                                return { status: 'sign-in' };
                            }

                            if (!response.ok) {
                                return { status: 'queued' };
                            }

                            return response.json().then(function (body) {
                                return { status: 'done', result: body.results[0] };
                            });
                        })
                        .catch(function () {
                            return { status: 'queued' };
                        });
                }

                function greet() {
                    fetch(WHOAMI, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                        .then(function (response) { return response.json(); })
                        .then(function (body) {
                            if (!body.signed_in) {
                                return;
                            }

                            who.hidden = false;
                            who.textContent = body.name;
                        })
                        .catch(function () { /* Offline. The scan still queues. */ });
                }

                function scan() {
                    again.hidden = true;
                    paint('wait', 'Checking you in', 'Hold still while your phone finds where you are.', null);

                    locate().then(function (position) {
                        return deliver({
                            // Minted here so that a scan sent twice, because the
                            // first reply never came back, is recognised as one
                            // scan rather than a departure.
                            client_uuid: (crypto.randomUUID ? crypto.randomUUID() : String(Date.now()) + Math.random().toString(16).slice(2)),
                            scanned_at: new Date().toISOString(),
                            latitude: position ? position.coords.latitude : null,
                            longitude: position ? position.coords.longitude : null,
                            accuracy_metres: position ? Math.round(position.coords.accuracy) : null,
                            was_queued: false,
                        });
                    }).then(function (outcome) {
                        if (outcome.status === 'sign-in') {
                            paint('bad', 'Sign in first', 'Open your portal, sign in, then scan the poster again.', null);
                            fetch(WHOAMI, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
                                .then(function (response) { return response.json(); })
                                .then(function (body) {
                                    if (body.portal_url) {
                                        signIn.href = body.portal_url;
                                        signIn.hidden = false;
                                    }
                                })
                                .catch(function () { /* Nothing to link to offline. */ });

                            return;
                        }

                        if (outcome.status === 'queued') {
                            paint('wait', 'Saved on this phone', 'There is no signal here. This will be sent the moment your phone is back online, with the time you actually scanned.', null);

                            return;
                        }

                        const result = outcome.result;

                        if (!result.recorded) {
                            paint('bad', result.kind === 'departure' ? 'Not recorded' : 'Not recorded', result.message, null);

                            return;
                        }

                        paint('good', result.kind === 'arrival' ? 'Arrival recorded' : 'Departure recorded', result.kind === 'arrival' ? 'Have a good day.' : 'Safe journey home.', result.at);
                    });
                }

                again.addEventListener('click', scan);

                // A scan queued on a phone with no signal goes out the next time
                // this page is opened with signal, as well as the moment the
                // browser says the connection is back.
                function flush() {
                    const worker = navigator.serviceWorker && navigator.serviceWorker.controller;

                    if (worker) {
                        worker.postMessage({ type: 'check-in-flush', endpoint: ENDPOINT, whoami: WHOAMI });
                    }
                }

                window.addEventListener('online', flush);

                if (navigator.serviceWorker) {
                    navigator.serviceWorker.register('/sw.js').catch(function () { /* Then there is no queue, only the direct post above. */ });
                }

                greet();
                flush();
                scan();
            })();
        </script>
    </body>
</html>
