/*
 * AkademicNest portal service worker.
 *
 * WHAT IT IS FOR: every portal (School Admin, Staff/Teacher, Student, Parent)
 * keeps working when the phone's data or the Wi-Fi is off.
 *
 *  - The build output (CSS, JS, Font Awesome fonts), the platform's icons and
 *    the offline page are stored when the worker installs.
 *  - Every signed-in page a person opens is kept on the device, network first:
 *    online they always get the live page, offline they get the copy from
 *    last time. After signing in, the pages linked from their portal are also
 *    fetched in the background (see "Warming" below), so the pages they have
 *    not opened yet are there too.
 *  - Forms submitted with no connection are held in IndexedDB and sent when
 *    the connection returns; see resources/js/offline/queue-core.js, which is
 *    pasted in below, and resources/js/offline/index.js for the page half.
 *
 * PRIVACY. These portals are behind a session and the devices are often
 * shared, so a kept page is filed under the ACCOUNT it was served to
 * (X-Offline-Scope, see App\Support\OfflineScope), only pages the server
 * labelled as keepable are kept (X-Offline-Cache, see
 * App\Http\Middleware\OfflineSupport), and signing out deletes that account's
 * pages from the device. A page kept for one account is never served while
 * another is the one in use.
 *
 * The CHECK-IN queue predates the general queue and keeps its own store and
 * endpoint; it is unchanged.
 */

const VERSION = '{{ $version }}';
const ASSET_CACHE = `akademicnest-assets-${VERSION}`;
const STATIC_CACHE = 'akademicnest-static-v1';
const PUBLIC_PAGES = 'akademicnest-pages-public';
const PAGE_PREFIX = 'akademicnest-pages-';
const MEDIA_PREFIX = 'akademicnest-media-';
const OFFLINE_URL = '{{ route('pwa.offline', absolute: false) }}';
const OFFLINE_CACHE = `akademicnest-offline-${VERSION}`;
const CHECK_IN_CACHE = `akademicnest-check-in-${VERSION}`;
const CHECK_IN_SYNC_TAG = 'akademicnest-check-ins';
const OFFLINE_SYNC_TAG = 'akademicnest-offline-sync';
const QUEUE_DB = 'akademicnest-check-ins';
const QUEUE_STORE = 'queued';
const PRECACHE = @json($precache);

// How long a page may take before the kept copy is shown instead. Only used
// when there IS a kept copy; a first visit waits for the network as long as it
// takes. Keeps a two-bar 2G connection from looking like a frozen app.
const NETWORK_TIMEOUT_MS = 6000;

// Matches /p/{portal key}/check-in/{token} and nothing else.
const CHECK_IN_PATH = /^\/p\/[^/]+\/check-in\/[^/]+\/?$/;

// Never fetched in the background: anything that signs out, downloads,
// exports, or changes something just by being opened.
const NEVER_WARM = /logout|sign-?out|download|export|\.pdf|\.csv|\.xlsx?|\.zip|\/delete|\/destroy|impersonat|keep-alive|\/offline|\/pwa\/|\/media\/|\/storage\/|notifications\/[^/]+\/read|read-all|mark-?read|\/print|\/stream|\/file\//i;

const WARM_LIMIT = 150;

/* ---------------------------------------------------------------------- */
/* The shared offline queue (resources/js/offline/queue-core.js)           */
/* ---------------------------------------------------------------------- */

{!! $queueCore !!}

const Q = self.AkademicNestOfflineQueue;

/* ---------------------------------------------------------------------- */
/* Install and activate                                                    */
/* ---------------------------------------------------------------------- */

self.addEventListener('install', (event) => {
    event.waitUntil(
        Promise.all([
            caches.open(OFFLINE_CACHE).then((cache) => cache.add(new Request(OFFLINE_URL, { cache: 'reload' }))).catch(() => undefined),
            // One file at a time, each allowed to fail: an icon that 404s is
            // not a reason to refuse to install the whole worker.
            caches.open(ASSET_CACHE).then((cache) =>
                Promise.all(PRECACHE.map((url) => cache.add(new Request(url, { cache: 'reload', credentials: 'same-origin' })).catch(() => undefined))),
            ),
        ]).then(() => self.skipWaiting()),
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
                                // Kept pages and media belong to an account,
                                // not to a release: a deploy must not empty a
                                // teacher's phone.
                                !key.startsWith(PAGE_PREFIX) &&
                                !key.startsWith(MEDIA_PREFIX) &&
                                key !== STATIC_CACHE &&
                                key !== ASSET_CACHE &&
                                key !== OFFLINE_CACHE &&
                                key !== CHECK_IN_CACHE,
                        )
                        .map((key) => caches.delete(key)),
                ),
            )
            .then(() => (self.registration.navigationPreload ? self.registration.navigationPreload.disable().catch(() => undefined) : undefined))
            .then(() => self.clients.claim()),
    );
});

/* ---------------------------------------------------------------------- */
/* Which account is in use                                                 */
/* ---------------------------------------------------------------------- */

// Most recent first. Kept in IndexedDB too, because the worker is stopped
// and restarted by the browser at will and loses everything in memory.
let recentScopes = null;
const clientScopes = new Map();

function loadScopes() {
    if (recentScopes) {
        return Promise.resolve(recentScopes);
    }

    return Q.metaGet('scopes')
        .then((value) => {
            recentScopes = Array.isArray(value) ? value : [];

            return recentScopes;
        })
        .catch(() => {
            recentScopes = [];

            return recentScopes;
        });
}

function touchScope(scope) {
    if (!scope) {
        return Promise.resolve();
    }

    return loadScopes().then((list) => {
        if (list[0] === scope) {
            return undefined;
        }

        recentScopes = [scope, ...list.filter((s) => s !== scope)].slice(0, 8);

        return Q.metaSet('scopes', recentScopes).catch(() => undefined);
    });
}

function forgetScope(scope) {
    return loadScopes()
        .then((list) => {
            recentScopes = list.filter((s) => s !== scope);

            return Q.metaSet('scopes', recentScopes).catch(() => undefined);
        })
        .then(() => Promise.all([caches.delete(PAGE_PREFIX + scope), caches.delete(MEDIA_PREFIX + scope), Q.metaSet('warm:' + scope, 0).catch(() => undefined)]));
}

/* ---------------------------------------------------------------------- */
/* Keeping and finding pages                                               */
/* ---------------------------------------------------------------------- */

/** Same path and query, no fragment: the key every page is filed under. */
function pageKey(url) {
    const u = new URL(url, self.location.origin);
    u.hash = '';

    return u.href;
}

/**
 * Keep a page if, and only as, the server labelled it.
 *
 * Stored as a NEW Response: one that came through a redirect carries a flag
 * that makes a browser refuse it as the answer to a later navigation.
 */
async function keepPage(requestUrl, response) {
    const label = response.headers.get('X-Offline-Cache');

    if (!response.ok || response.status !== 200 || (label !== 'private' && label !== 'public')) {
        return null;
    }

    const scope = label === 'private' ? response.headers.get('X-Offline-Scope') : null;

    if (label === 'private' && !scope) {
        return null;
    }

    const body = await response.clone().blob();
    const headers = new Headers(response.headers);
    headers.set('X-Offline-Saved-At', new Date().toISOString());
    headers.delete('Set-Cookie');

    const stored = () => new Response(body, { status: 200, statusText: 'OK', headers });
    const cache = await caches.open(scope ? PAGE_PREFIX + scope : PUBLIC_PAGES);

    await cache.put(pageKey(requestUrl), stored());

    // Reached through a redirect (the admin app starts at its login page,
    // which sends a signed-in admin on to the dashboard): remember where it
    // led, so the same start works offline.
    if (response.url && pageKey(response.url) !== pageKey(requestUrl)) {
        await cache.put(pageKey(response.url), stored());

        if (scope) {
            await Q.metaSet('alias:' + pageKey(requestUrl), { to: pageKey(response.url), scope }).catch(() => undefined);
        }
    }

    if (scope) {
        await touchScope(scope);
    }

    return scope;
}

/**
 * The kept copy of a page, from the account in use most recently first.
 */
async function findPage(url) {
    const key = pageKey(url);
    const scopes = await loadScopes();

    for (const scope of scopes) {
        const cache = await caches.open(PAGE_PREFIX + scope);
        const hit = await cache.match(key);

        if (hit) {
            return hit;
        }
    }

    const alias = await Q.metaGet('alias:' + key).catch(() => null);

    if (alias && scopes.includes(alias.scope)) {
        const cache = await caches.open(PAGE_PREFIX + alias.scope);

        if (await cache.match(alias.to)) {
            return Response.redirect(alias.to, 302);
        }
    }

    const publicHit = await (await caches.open(PUBLIC_PAGES)).match(key);

    if (publicHit) {
        return publicHit;
    }

    // The same page with different filters: better than nothing, and the
    // banner on the page says it is a saved copy.
    for (const scope of scopes) {
        const cache = await caches.open(PAGE_PREFIX + scope);
        const hit = await cache.match(key, { ignoreSearch: true });

        if (hit) {
            return hit;
        }
    }

    return null;
}

/**
 * A kept page, marked so the page itself can tell the person it is the copy
 * saved at a given time and not the live one.
 */
async function markAsCopy(response) {
    if (response.status !== 200) {
        return response;
    }

    const savedAt = response.headers.get('X-Offline-Saved-At') || '';
    const html = await response.text();
    const marker = `<meta name="akademicnest-offline-copy" content="${savedAt}">`;
    const marked = /<head[^>]*>/i.test(html) ? html.replace(/<head[^>]*>/i, (m) => m + marker) : marker + html;
    const headers = new Headers(response.headers);
    headers.delete('Content-Length');

    return new Response(marked, { status: 200, headers });
}

async function offlinePage() {
    const hit = (await caches.match(OFFLINE_URL)) || null;

    return (
        hit ||
        new Response('<!doctype html><meta charset="utf-8"><title>Offline</title><p>You are offline.</p>', {
            status: 503,
            headers: { 'Content-Type': 'text/html; charset=utf-8' },
        })
    );
}

function timeout(ms) {
    return new Promise((resolve) => setTimeout(() => resolve(null), ms));
}

async function handleNavigation(event) {
    const request = event.request;
    const network = fetch(request).then(
        async (response) => {
            // Kept in the background; the person does not wait for it.
            event.waitUntil(keepPage(request.url, response.clone()).catch(() => undefined));

            return response;
        },
        () => null,
    );

    const kept = await findPage(request.url).catch(() => null);

    if (kept) {
        // A slow connection gets the kept copy rather than a blank screen.
        const first = await Promise.race([network, timeout(NETWORK_TIMEOUT_MS)]);

        if (first) {
            return first;
        }

        return markAsCopy(kept);
    }

    const response = await network;

    if (response) {
        return response;
    }

    return offlinePage();
}

/* ---------------------------------------------------------------------- */
/* A form posted while the connection was failing                          */
/* ---------------------------------------------------------------------- */

/**
 * The page normally catches a form submitted offline before it leaves (see
 * resources/js/offline/index.js). This catches the rest: a connection that
 * says it is online but cannot reach the server. The form is kept exactly as
 * submitted and sent later, and the person is told so instead of seeing a
 * browser error that has lost what they typed.
 */
async function handleFormPost(event) {
    const request = event.request;
    const url = new URL(request.url);
    const copy = request.clone();

    try {
        return await fetch(request);
    } catch (networkError) {
        // Signing out is not something to do later: there is no session to
        // end without the server. The page half clears the device's copy.
        if (/logout|sign-?out/i.test(url.pathname)) {
            return offlinePage();
        }

        let fields;

        try {
            fields = Q.serializeFormData(await copy.formData());
        } catch (e) {
            return savedPage(false, request.referrer);
        }

        const client = event.clientId ? await self.clients.get(event.clientId) : null;
        const scope = (client && clientScopes.get(client.id)) || (await loadScopes())[0] || null;
        const key = fields.find((pair) => pair[0] === '_offline_key');

        await Q.put({
            id: Q.uuid(),
            key: (key && typeof key[1] === 'string' && key[1]) || Q.uuid(),
            kind: 'form',
            scope,
            method: 'POST',
            url: request.url,
            fields,
            label: 'Form saved offline',
            sourceUrl: request.referrer || null,
            createdAt: Date.now(),
            status: 'pending',
            attempts: 0,
            fingerprint: Q.fingerprint('POST', request.url, fields),
        });

        registerSync();
        broadcast({ type: 'queue-changed' });

        return savedPage(true, request.referrer);
    }
}

function savedPage(saved, back) {
    const target = back && new URL(back).origin === self.location.origin ? back : '/';
    const title = saved ? 'Saved on this device' : 'Not sent';
    const text = saved
        ? 'There is no connection, so this was saved on your device. It will be sent automatically when you are back online. You can carry on working.'
        : 'There is no connection and this could not be kept. Go back and try again.';

    return new Response(
        `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>${title}</title>
<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;background:#f8fafc;color:#0f172a}
.c{max-width:380px;background:#fff;border:1px solid #e2e8f0;border-radius:14px;padding:28px 22px;text-align:center}h1{font-size:19px;margin:0 0 8px}p{font-size:14px;line-height:1.55;color:#475569;margin:0}
a{display:block;margin-top:20px;padding:12px;border-radius:9px;background:#4f46e5;color:#fff;font-weight:700;text-decoration:none}</style></head>
<body><div class="c"><h1>${saved ? '&#10003; ' : ''}${title}</h1><p>${text}</p><a href="${target.replace(/"/g, '&quot;')}">Continue</a></div>
<script>setTimeout(function(){ if (history.length > 1) { history.back(); } else { location.href = ${JSON.stringify(target)}; } }, ${saved ? 2500 : 6000});</` + `script></body></html>`,
        { status: 200, headers: { 'Content-Type': 'text/html; charset=utf-8', 'Cache-Control': 'no-store' } },
    );
}

/* ---------------------------------------------------------------------- */
/* Static files and photographs                                            */
/* ---------------------------------------------------------------------- */

function cacheFirst(request, cacheName) {
    return caches.match(request).then(
        (hit) =>
            hit ||
            fetch(request).then((response) => {
                if (response.ok || response.type === 'opaque') {
                    const copy = response.clone();
                    caches.open(cacheName).then((cache) => cache.put(request, copy)).catch(() => undefined);
                }

                return response;
            }),
    );
}

/** Network first, the kept copy when there is no network. */
function networkFirst(request, cacheName) {
    return fetch(request)
        .then((response) => {
            if (response.ok) {
                const copy = response.clone();
                caches.open(cacheName).then((cache) => cache.put(request, copy)).catch(() => undefined);
            }

            return response;
        })
        .catch(() => caches.match(request).then((hit) => hit || Response.error()));
}

async function mediaCacheName(event) {
    const client = event.clientId ? await self.clients.get(event.clientId) : null;
    const scope = (client && clientScopes.get(client.id)) || (await loadScopes())[0];

    return scope ? MEDIA_PREFIX + scope : null;
}

/* ---------------------------------------------------------------------- */
/* Routing                                                                 */
/* ---------------------------------------------------------------------- */

const FONT_HOSTS = ['fonts.bunny.net', 'fonts.googleapis.com', 'fonts.gstatic.com'];

self.addEventListener('fetch', (event) => {
    const request = event.request;
    const url = new URL(request.url);

    // Forms submitted by navigating. Only POST from our own pages.
    if (request.method === 'POST' && request.mode === 'navigate' && url.origin === self.location.origin) {
        event.respondWith(handleFormPost(event));

        return;
    }

    // Any other write is the page's business (resources/js/offline/index.js
    // queues failed fetch() writes itself).
    if (request.method !== 'GET') {
        return;
    }

    // The fonts the layouts link: kept, so an offline page looks the same.
    if (FONT_HOSTS.includes(url.hostname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));

        return;
    }

    if (url.origin !== self.location.origin) {
        // Photographs addressed from APP_URL on a school's own domain.
        if (request.destination === 'image') {
            event.respondWith(
                mediaCacheName(event).then((name) => (name ? networkFirst(request, name) : fetch(request))),
            );
        }

        return;
    }

    // Never answered from a kept copy.
    if (url.pathname === '/sw.js' || url.pathname === Q.SESSION_URL || url.pathname.startsWith('/session/')) {
        return;
    }

    // Build assets: cache first. The filename contains a hash of the
    // contents, so a hit is always the right file.
    if (url.pathname.startsWith('/build/')) {
        event.respondWith(cacheFirst(request, ASSET_CACHE));

        return;
    }

    // Platform images and icons: the same for everybody.
    if (url.pathname.startsWith('/images/') || url.pathname.startsWith('/pwa/') || /^\/(favicon|apple-touch-icon)/.test(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));

        return;
    }

    // The check-in page, kept for the gate with no signal. Unchanged.
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

    if (request.mode === 'navigate') {
        event.respondWith(handleNavigation(event));

        return;
    }

    // Uploaded logos and photographs shown inside a portal: personal, so
    // kept with the account's pages and deleted with them.
    if (request.destination === 'image') {
        event.respondWith(
            mediaCacheName(event).then((name) => (name ? networkFirst(request, name) : fetch(request))),
        );
    }
});

/* ---------------------------------------------------------------------- */
/* Warming: fetching a portal's pages after sign-in                        */
/* ---------------------------------------------------------------------- */

let warming = null;

function linksIn(html, base, prefixes, never = []) {
    const found = [];
    const pattern = /href\s*=\s*["']([^"'#]+)["']/gi;
    let match;

    while ((match = pattern.exec(html))) {
        let href = match[1].replace(/&amp;/g, '&');

        try {
            const u = new URL(href, base);

            if (u.origin !== self.location.origin || NEVER_WARM.test(u.pathname + u.search) || never.some((re) => re.test(u.pathname))) {
                continue;
            }

            if (!prefixes.some((prefix) => u.pathname.startsWith(prefix))) {
                continue;
            }

            u.hash = '';
            found.push(u.href);
        } catch (e) {
            // Not a URL.
        }
    }

    return found;
}

async function warm(scope, urls, prefixes, never = []) {
    if (warming) {
        return warming;
    }

    warming = (async () => {
        const seen = new Set();
        let queue = urls.map((u) => ({ url: u, depth: 1 }));
        let fetched = 0;

        while (queue.length && fetched < WARM_LIMIT) {
            const batch = queue.splice(0, 3);

            await Promise.all(
                batch.map(async ({ url, depth }) => {
                    const key = pageKey(url);

                    if (seen.has(key) || NEVER_WARM.test(new URL(key).pathname) || never.some((re) => re.test(new URL(key).pathname))) {
                        return;
                    }

                    seen.add(key);
                    fetched++;

                    try {
                        const response = await fetch(key, {
                            credentials: 'same-origin',
                            headers: { 'X-Offline-Warm': '1', Accept: 'text/html' },
                        });

                        // Only pages that are this account's own.
                        if (response.headers.get('X-Offline-Scope') !== scope) {
                            return;
                        }

                        const html = await response.clone().text();
                        await keepPage(key, response);

                        if (depth < 2) {
                            linksIn(html, key, prefixes, never).forEach((next) => queue.push({ url: next, depth: depth + 1 }));
                        }
                    } catch (e) {
                        // Offline part way through: whatever was kept stays.
                    }
                }),
            );
        }

        await Q.metaSet('warm:' + scope, Date.now()).catch(() => undefined);
        broadcast({ type: 'warm-done', scope, pages: fetched });
    })().finally(() => {
        warming = null;
    });

    return warming;
}

/* ---------------------------------------------------------------------- */
/* Talking to pages                                                        */
/* ---------------------------------------------------------------------- */

function broadcast(message) {
    return self.clients.matchAll({ includeUncontrolled: true, type: 'window' }).then((clients) => clients.forEach((client) => client.postMessage(message)));
}

function registerSync() {
    if (self.registration.sync) {
        self.registration.sync.register(OFFLINE_SYNC_TAG).catch(() => undefined);
    }
}

async function savedPages() {
    const scopes = await loadScopes();
    const list = [];

    for (const scope of scopes.slice(0, 1)) {
        const cache = await caches.open(PAGE_PREFIX + scope);
        const keys = await cache.keys();

        for (const request of keys) {
            const response = await cache.match(request);
            const html = response ? await response.clone().text() : '';
            const title = (html.match(/<title[^>]*>([^<]*)<\/title>/i) || [])[1] || new URL(request.url).pathname;

            list.push({ url: request.url, title: title.trim(), savedAt: response && response.headers.get('X-Offline-Saved-At') });
        }
    }

    return list;
}

async function flushFromWorker() {
    const last = await Q.metaGet('lastActivity').catch(() => null);
    const idleSeconds = last ? Math.max(0, (Date.now() - last) / 1000) : 9999;
    const summary = await Q.flush({ idleSeconds });

    broadcast({ type: 'sync-result', summary });

    return summary;
}

self.addEventListener('message', (event) => {
    const data = event.data || {};
    const reply = event.ports && event.ports[0];

    if (data.type === 'hello') {
        if (event.source && data.scope) {
            clientScopes.set(event.source.id, data.scope);
        }

        // The installed app's start page may be one that only redirects a
        // signed-in person onward (the School Admin app starts at its login),
        // and a redirect cannot be followed offline. Remember where it leads.
        const alias =
            data.scope && data.startUrl && data.homeUrl && pageKey(data.startUrl) !== pageKey(data.homeUrl)
                ? Q.metaSet('alias:' + pageKey(data.startUrl), { to: pageKey(data.homeUrl), scope: data.scope }).catch(() => undefined)
                : Promise.resolve();

        event.waitUntil(Promise.all([touchScope(data.scope), alias]));

        return;
    }

    if (data.type === 'warm' && data.scope) {
        const never = (data.never || [])
            .map((source) => {
                try {
                    return new RegExp(source);
                } catch (e) {
                    return null;
                }
            })
            .filter(Boolean);

        event.waitUntil(warm(data.scope, data.urls || [], data.prefixes || ['/'], never));

        return;
    }

    if (data.type === 'keep-page' && data.url) {
        // The page that is open right now, which the browser may have loaded
        // before this worker was in control.
        event.waitUntil(
            fetch(data.url, { credentials: 'same-origin', headers: { 'X-Offline-Warm': '1' } })
                .then((response) => keepPage(data.url, response))
                .catch(() => undefined),
        );

        return;
    }

    if (data.type === 'forget-scope' && data.scope) {
        event.waitUntil(forgetScope(data.scope).then(() => reply && reply.postMessage({ ok: true })));

        return;
    }

    if (data.type === 'saved-pages') {
        event.waitUntil(savedPages().then((list) => reply && reply.postMessage(list)).catch(() => reply && reply.postMessage([])));

        return;
    }

    if (data.type === 'sync') {
        event.waitUntil(flushFromWorker().catch(() => undefined));

        return;
    }

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

    if (event.tag === OFFLINE_SYNC_TAG) {
        event.waitUntil(
            flushFromWorker().then((summary) => {
                // Still things to send: ask the browser to try again later.
                if (summary && summary.retry > 0) {
                    return Promise.reject(new Error('retry'));
                }

                return undefined;
            }),
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
