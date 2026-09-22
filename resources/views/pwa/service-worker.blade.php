/*
 * AkademicNest portal service worker.
 *
 * ITS MOST IMPORTANT PROPERTY IS WHAT IT DOES NOT DO. It never caches a page.
 *
 * Every portal here is multi-tenant and behind a session. A cached dashboard
 * is a dashboard that can be handed to whoever opens the browser next, and the
 * devices these apps get installed on are shared family phones and school
 * office machines. There is no attacker in that story and it is still a data
 * leak. So HTML, JSON and anything with a session behind it goes to the
 * network every time, and if the network is not there the request fails
 * honestly.
 *
 * What it does cache is the build output, the hashed CSS and JS under
 * /build/, which is byte-identical for every school and carries nothing
 * personal. Those filenames change whenever the contents do, so a cached one
 * is never stale.
 *
 * THERE IS NOW ONE EXCEPTION, and it is a deliberate one: the QR check-in
 * page. That page is written to contain no name and no session, see
 * resources/views/check-in/scan.blade.php, precisely so that it can be kept
 * here without breaking the rule above, because a gate with no signal is
 * exactly where somebody needs it to open. Who is signed in is fetched
 * separately by that page and is never cached.
 *
 * The worker also owns the CHECK-IN QUEUE. A scan made with no signal is held
 * in IndexedDB with the time it really happened and sent later, and the queue
 * lives here rather than in the page so that there is one copy of it and so
 * that Background Sync can drain it without a tab being open.
 *
 * The reason it exists at all is that a browser will not offer to install an
 * app without one.
 */

const VERSION = '{{ $version }}';
const ASSET_CACHE = `akademicnest-assets-${VERSION}`;
const OFFLINE_URL = '{{ route('pwa.offline', absolute: false) }}';
const OFFLINE_CACHE = `akademicnest-offline-${VERSION}`;
const CHECK_IN_CACHE = `akademicnest-check-in-${VERSION}`;
const CHECK_IN_SYNC_TAG = 'akademicnest-check-ins';
const QUEUE_DB = 'akademicnest-check-ins';
const QUEUE_STORE = 'queued';

// Matches /p/{portal key}/check-in/{token} and nothing else.
const CHECK_IN_PATH = /^\/p\/[^/]+\/check-in\/[^/]+\/?$/;

self.addEventListener('install', (event) => {
    event.waitUntil(
        caches
            .open(OFFLINE_CACHE)
            .then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' })))
            // An offline page that could not be fetched is not a reason to
            // refuse the whole installation.
            .catch(() => undefined)
            .then(() => self.skipWaiting()),
    );
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches
            .keys()
            .then((keys) =>
                Promise.all(
                    keys
                        .filter(
                            (key) =>
                                key.startsWith('akademicnest-') &&
                                key !== ASSET_CACHE &&
                                key !== OFFLINE_CACHE &&
                                key !== CHECK_IN_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('fetch', (event) => {
    const request = event.request;

    // Anything that changes state is none of this worker's business.
    if (request.method !== 'GET') {
        return;
    }

    const url = new URL(request.url);

    if (url.origin !== self.location.origin) {
        return;
    }

    // Build assets: cache first. The filename contains a hash of the
    // contents, so a hit is always the right file.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(
            caches.match(request).then(
                (hit) =>
                    hit ||
                    fetch(request).then((response) => {
                        if (response.ok) {
                            const copy = response.clone();
                            caches.open(ASSET_CACHE).then((cache) => cache.put(request, copy));
                        }

                        return response;
                    }),
            ),
        );

        return;
    }

    // The one page that is kept, for the reason at the top of this file: a
    // phone at a gate with no signal has to be able to open it. Network first,
    // so it is never stale, falling back to the copy from last time.
    if (request.mode === 'navigate' && CHECK_IN_PATH.test(url.pathname)) {
        event.respondWith(
            fetch(request)
                .then((response) => {
                    if (response.ok) {
                        const copy = response.clone();
                        caches.open(CHECK_IN_CACHE).then((cache) => cache.put(request, copy));
                    }

                    return response;
                })
                .catch(() => caches.match(request).then((hit) => hit || caches.match(OFFLINE_URL))),
        );

        return;
    }

    // Everything else, every page, every API call, is network only. When a
    // navigation fails because the device is offline, show the offline page
    // rather than the browser's dinosaur, so an installed app still looks like
    // an app. A FAILED PAGE IS NEVER CACHED AND A SUCCESSFUL ONE IS NEVER
    // STORED; this only substitutes a static file for a network error.
    if (request.mode === 'navigate') {
        event.respondWith(
            fetch(request).catch(() =>
                caches.match(OFFLINE_URL).then((hit) => hit || new Response('You are offline.', {
                    status: 503,
                    headers: { 'Content-Type': 'text/plain' },
                })),
            ),
        );
    }
});

/*
 * The check-in queue.
 *
 * A scan carries its own time and its own id, so holding one back costs
 * nothing but the delay: whenever it finally goes out it is recorded at the
 * moment it was made, and sending it twice lands on the same row.
 */

function openQueue() {
    return new Promise((resolve, reject) => {
        const request = indexedDB.open(QUEUE_DB, 1);

        request.onupgradeneeded = () => {
            if (!request.result.objectStoreNames.contains(QUEUE_STORE)) {
                request.result.createObjectStore(QUEUE_STORE, { keyPath: 'client_uuid' });
            }
        };

        request.onsuccess = () => resolve(request.result);
        request.onerror = () => reject(request.error);
    });
}

function withStore(mode, work) {
    return openQueue().then(
        (db) =>
            new Promise((resolve, reject) => {
                const transaction = db.transaction(QUEUE_STORE, mode);
                const result = work(transaction.objectStore(QUEUE_STORE));

                transaction.oncomplete = () => resolve(result && result.result !== undefined ? result.result : undefined);
                transaction.onerror = () => reject(transaction.error);
            }),
    );
}

function rememberScan(entry) {
    return withStore('readwrite', (store) => store.put(entry));
}

function forgetScan(clientUuid) {
    return withStore('readwrite', (store) => store.delete(clientUuid));
}

function queuedScans() {
    return withStore('readonly', (store) => store.getAll());
}

// The live CSRF token. Never taken from the cached HTML, which may be older
// than the session it would be posted against.
function freshToken(whoami) {
    return fetch(whoami, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then((response) => (response.ok ? response.json() : null))
        .then((body) => (body ? body.csrf_token : null));
}

function send(endpoint, whoami, scans) {
    return freshToken(whoami).then((token) => {
        if (!token) {
            return { ok: false, status: 0 };
        }

        return fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': token,
            },
            body: JSON.stringify({ scans: scans }),
        }).then((response) => {
            if (!response.ok) {
                return { ok: false, status: response.status };
            }

            return response.json().then((body) => ({ ok: true, status: 200, results: body.results }));
        });
    });
}

function recordScan(endpoint, whoami, scan) {
    return send(endpoint, whoami, [scan])
        .then((reply) => {
            if (reply.ok) {
                return { status: 'done', result: reply.results[0] };
            }

            // Signed out is not a failure to retry blindly: the scan waits,
            // and goes out after the next sign-in rather than being lost.
            if (reply.status === 401) {
                return rememberScan({ ...scan, endpoint, whoami, was_queued: true }).then(() => ({ status: 'sign-in' }));
            }

            return Promise.reject(reply);
        })
        .catch(() =>
            rememberScan({ ...scan, endpoint, whoami, was_queued: true })
                .then(() => (self.registration.sync ? self.registration.sync.register(CHECK_IN_SYNC_TAG).catch(() => undefined) : undefined))
                .then(() => ({ status: 'queued' })),
        );
}

function flushQueue() {
    return queuedScans().then((entries) => {
        if (!entries || entries.length === 0) {
            return undefined;
        }

        // Grouped by the address they belong to: one phone can hold scans for
        // more than one school.
        const byEndpoint = new Map();

        entries.forEach((entry) => {
            const list = byEndpoint.get(entry.endpoint) || [];
            list.push(entry);
            byEndpoint.set(entry.endpoint, list);
        });

        return Promise.all(
            [...byEndpoint.entries()].map(([endpoint, list]) =>
                send(
                    endpoint,
                    list[0].whoami,
                    list.map((entry) => ({
                        client_uuid: entry.client_uuid,
                        scanned_at: entry.scanned_at,
                        latitude: entry.latitude,
                        longitude: entry.longitude,
                        accuracy_metres: entry.accuracy_metres,
                        was_queued: true,
                    })),
                )
                    .then((reply) => (reply.ok ? Promise.all(list.map((entry) => forgetScan(entry.client_uuid))) : undefined))
                    .catch(() => undefined),
            ),
        );
    });
}

self.addEventListener('message', (event) => {
    const data = event.data || {};
    const reply = event.ports && event.ports[0];

    if (data.type === 'check-in') {
        event.waitUntil(
            recordScan(data.endpoint, data.whoami, data.scan).then((outcome) => {
                if (reply) {
                    reply.postMessage(outcome);
                }
            }),
        );

        return;
    }

    if (data.type === 'check-in-flush') {
        event.waitUntil(flushQueue());
    }
});

self.addEventListener('sync', (event) => {
    if (event.tag === CHECK_IN_SYNC_TAG) {
        event.waitUntil(flushQueue());
    }
});
