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
 * What it does cache is the build output - the hashed CSS and JS under
 * /build/ - which is byte-identical for every school and carries nothing
 * personal. Those filenames change whenever the contents do, so a cached one
 * is never stale.
 *
 * The reason it exists at all is that a browser will not offer to install an
 * app without one.
 */

const VERSION = '{{ $version }}';
const ASSET_CACHE = `akademicnest-assets-${VERSION}`;
const OFFLINE_URL = '{{ route('pwa.offline', absolute: false) }}';
const OFFLINE_CACHE = `akademicnest-offline-${VERSION}`;

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
                        .filter((key) => key.startsWith('akademicnest-') && key !== ASSET_CACHE && key !== OFFLINE_CACHE)
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

    // Everything else - every page, every API call - is network only. When a
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
