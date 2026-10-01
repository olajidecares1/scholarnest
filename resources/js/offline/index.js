/**
 * Using a portal with no connection: the page's half.
 *
 * The service worker (resources/views/pwa/service-worker.blade.php) keeps the
 * pages and the files they need. This module makes the pages BEHAVE offline:
 *
 *  1. FORMS. A form submitted with no connection is kept on the device, with
 *     every field and file, instead of failing. It is sent by itself when the
 *     connection returns. Each form also carries a random key, so a write that
 *     arrives twice (or a Save tapped twice) is only done once; see
 *     App\Http\Middleware\OfflineSupport.
 *  2. PAGE SCRIPTS. A write a page makes with fetch() (a CBT answer, a
 *     signature, a result remark) is kept the same way when it cannot be sent,
 *     and the page is told it succeeded, which from the person's side it has.
 *  3. SYNC. Kept writes go out oldest first, once, for the account that made
 *     them, as soon as the connection is back: on reconnect, on opening the
 *     app, every 20 seconds while anything is waiting, and through Background
 *     Sync where the browser has it (Android) even with the app closed.
 *  4. TELLING THE PERSON. A small status pill says when they are offline, when
 *     changes are waiting, sending, saved or need attention, and when a page
 *     is the copy saved on the device rather than the live one.
 *  5. WARMING. After signing in, the pages linked from the portal are fetched
 *     in the background, so pages not yet opened are available offline too.
 *
 * Some things cannot happen without the server at all: signing in, changing a
 * password, paying, and downloads generated on demand. Those are named to the
 * person ("needs a connection") rather than failing with an error, and a
 * payment can be set to remind them as soon as they are back online.
 */

import './queue-core.js';

const Q = globalThis.AkademicNestOfflineQueue;

const SYNC_TAG = 'akademicnest-offline-sync';
const WARM_EVERY_MS = 20 * 60 * 1000;
const SYNC_EVERY_MS = 20 * 1000;

// Needs the server there and then. Matched on the form's action. The server
// adds the exact paths by route name (App\Support\OfflineRoutes), because
// the School Admin's paths are obfuscated and say nothing on their own.
const ONLINE_ONLY = /\/(login|sign-?in|register|password|forgot|reset-password|verify|two-factor|subscriptions?|payments?|pay|checkout|paystack|flutterwave|billing|renew|upgrade)(\/|$|\?)/i;
const PAYMENT = /\/(subscriptions?|payments?|pay|checkout|paystack|flutterwave|billing|renew|upgrade)(\/|$|\?)/i;
const LOGOUT = /\/(logout|sign-?out)(\/|$|\?)/i;

let routePatterns = { onlineOnly: [], logout: [], neverWarm: [] };

function compilePatterns(list) {
    return (list || [])
        .map((source) => {
            try {
                return new RegExp(source);
            } catch (e) {
                return null;
            }
        })
        .filter(Boolean);
}

const matchesAny = (patterns, path) => patterns.some((re) => re.test(path));
const isLogout = (path) => LOGOUT.test(path) || matchesAny(routePatterns.logout, path);
const isOnlineOnly = (path) => ONLINE_ONLY.test(path) || matchesAny(routePatterns.onlineOnly, path);

// fetch() writes that return a file or a preview rather than saving anything:
// keeping one for later would hand the page JSON where it expects a file.
const FETCH_ONLINE_ONLY = /download|preview|sample|export|search|keep-alive|\/offline\/|\/check-in\//i;

let settings = null;
let lastActivity = Date.now();
let reachable = true;
let syncing = false;
let syncTimer = null;
let ui = null;
const nativeFetch = window.fetch.bind(window);

/* ---------------------------------------------------------------------- */
/* Small helpers                                                           */
/* ---------------------------------------------------------------------- */

const isOnline = () => navigator.onLine !== false && reachable;

const sameOrigin = (url) => {
    try {
        return new URL(url, window.location.href).origin === window.location.origin;
    } catch (e) {
        return false;
    }
};

const idleSeconds = () => Math.max(0, Math.floor((Date.now() - lastActivity) / 1000));

function worker() {
    return navigator.serviceWorker && navigator.serviceWorker.controller ? navigator.serviceWorker.controller : null;
}

function tellWorker(message) {
    const target = worker();

    if (target) {
        target.postMessage(message);

        return true;
    }

    return false;
}

function askWorker(message, timeoutMs = 4000) {
    return new Promise((resolve) => {
        const target = worker();

        if (!target) {
            resolve(null);

            return;
        }

        const channel = new MessageChannel();
        const timer = setTimeout(() => resolve(null), timeoutMs);
        channel.port1.onmessage = (event) => {
            clearTimeout(timer);
            resolve(event.data);
        };
        target.postMessage(message, [channel.port2]);
    });
}

function registerBackgroundSync() {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    navigator.serviceWorker.ready
        .then((registration) => (registration.sync ? registration.sync.register(SYNC_TAG) : undefined))
        .catch(() => undefined);
}

function formatTime(value) {
    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }

    const sameDay = date.toDateString() === new Date().toDateString();

    return sameDay
        ? date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : date.toLocaleString([], { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function escapeHtml(text) {
    return String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
}

/** What to call a kept form in the list: the page heading and the button. */
function describeForm(form, submitter) {
    const explicit = form.getAttribute('data-offline-label');

    if (explicit) {
        return explicit;
    }

    const heading = (document.querySelector('main h1, h1')?.textContent || document.title || 'Form').trim().replace(/\s+/g, ' ');
    const action = (submitter?.textContent || submitter?.value || '').trim().replace(/\s+/g, ' ');

    return (action ? `${heading} – ${action}` : heading).slice(0, 120);
}

/* ---------------------------------------------------------------------- */
/* Status UI                                                               */
/* ---------------------------------------------------------------------- */

const STYLE = `
.an-off{position:fixed;left:12px;bottom:calc(12px + env(safe-area-inset-bottom));z-index:2147483000;font-family:inherit;font-size:13px}
@media (max-width:1023px){.an-off{bottom:calc(78px + env(safe-area-inset-bottom))}}
.an-off__pill{display:flex;align-items:center;gap:8px;max-width:calc(100vw - 24px);border:0;border-radius:999px;padding:8px 14px;font-weight:600;color:#fff;background:#334155;box-shadow:0 6px 20px rgba(15,23,42,.25);cursor:pointer;line-height:1.3;text-align:left}
.an-off__pill[data-tone=offline]{background:#b45309}.an-off__pill[data-tone=sync]{background:#2563eb}.an-off__pill[data-tone=ok]{background:#15803d}.an-off__pill[data-tone=bad]{background:#b91c1c}
.an-off__dot{width:8px;height:8px;border-radius:50%;background:currentColor;flex:none;opacity:.9}
.an-off__panel{position:absolute;left:0;bottom:calc(100% + 8px);width:min(380px,calc(100vw - 24px));max-height:60vh;overflow:auto;background:#fff;color:#0f172a;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 12px 32px rgba(15,23,42,.25);padding:14px}
.an-off__panel h3{margin:0 0 4px;font-size:15px;font-weight:800}.an-off__panel p{margin:0 0 10px;color:#475569;font-size:12.5px;line-height:1.5}
.an-off__item{border-top:1px solid #e2e8f0;padding:10px 0}.an-off__item b{display:block;font-size:13px}.an-off__item small{color:#64748b;display:block;margin-top:2px}
.an-off__err{color:#b91c1c;font-size:12px;margin-top:4px}
.an-off__acts{display:flex;gap:6px;margin-top:8px;flex-wrap:wrap}.an-off__acts button,.an-off__acts a{border:1px solid #cbd5e1;background:#f8fafc;color:#0f172a;border-radius:8px;padding:5px 10px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none}
.an-off__acts .an-primary{background:#4f46e5;border-color:#4f46e5;color:#fff}
.an-off__toast{position:fixed;left:50%;transform:translateX(-50%);top:calc(14px + env(safe-area-inset-top));z-index:2147483001;max-width:min(440px,calc(100vw - 24px));background:#0f172a;color:#fff;border-radius:12px;padding:11px 16px;font-size:13.5px;line-height:1.45;box-shadow:0 10px 30px rgba(15,23,42,.35)}
.an-off__toast[data-tone=ok]{background:#15803d}.an-off__toast[data-tone=bad]{background:#b91c1c}.an-off__toast[data-tone=offline]{background:#b45309}
.an-off-form-note{margin:10px 0;padding:10px 12px;border-radius:10px;background:#fef3c7;color:#78350f;font-size:13px;line-height:1.45;border:1px solid #fcd34d}
@media (prefers-color-scheme:dark){.an-off__panel{background:#0f172a;color:#e2e8f0;border-color:#334155}.an-off__panel p,.an-off__item small{color:#94a3b8}.an-off__item{border-color:#334155}.an-off__acts button,.an-off__acts a{background:#1e293b;color:#e2e8f0;border-color:#334155}}
@media print{.an-off,.an-off__toast{display:none!important}}
`;

function buildUi() {
    if (ui) {
        return ui;
    }

    const style = document.createElement('style');
    style.textContent = STYLE;
    document.head.append(style);

    const wrap = document.createElement('div');
    wrap.className = 'an-off';
    wrap.setAttribute('role', 'status');
    wrap.setAttribute('aria-live', 'polite');
    wrap.hidden = true;

    const pill = document.createElement('button');
    pill.type = 'button';
    pill.className = 'an-off__pill';
    pill.innerHTML = '<span class="an-off__dot" aria-hidden="true"></span><span class="an-off__text"></span>';

    const panel = document.createElement('div');
    panel.className = 'an-off__panel';
    panel.hidden = true;

    pill.addEventListener('click', () => {
        panel.hidden = !panel.hidden;

        if (!panel.hidden) {
            renderPanel();
        }
    });

    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) {
            panel.hidden = true;
        }
    });

    wrap.append(panel, pill);
    document.body.append(wrap);

    ui = { wrap, pill, panel, text: pill.querySelector('.an-off__text'), hideTimer: null };

    return ui;
}

function toast(message, tone = 'info', ms = 4500) {
    buildUi();
    document.querySelectorAll('.an-off__toast').forEach((el) => el.remove());

    const el = document.createElement('div');
    el.className = 'an-off__toast';
    el.dataset.tone = tone;
    el.setAttribute('role', 'alert');
    el.textContent = message;
    document.body.append(el);
    setTimeout(() => el.remove(), ms);
}

function offlineCopySavedAt() {
    return document.querySelector('meta[name="akademicnest-offline-copy"]')?.content || null;
}

async function refreshStatus(flash = null) {
    const u = buildUi();
    const entries = await Q.all().catch(() => []);
    const mine = entries.filter((e) => !settings.offlineScope || !e.scope || e.scope === settings.offlineScope);
    const failed = mine.filter((e) => e.status === 'failed').length;
    const needSignIn = mine.filter((e) => e.status === 'auth').length;
    const waiting = mine.length - failed;
    const copy = offlineCopySavedAt();

    let tone = null;
    let text = '';

    if (!isOnline()) {
        tone = 'offline';
        text = waiting
            ? `Offline · ${waiting} change${waiting === 1 ? '' : 's'} saved on this device`
            : copy
              ? `Offline · saved copy from ${formatTime(copy)}`
              : 'Offline · you can keep working';
    } else if (syncing && waiting) {
        tone = 'sync';
        text = `Syncing ${waiting} change${waiting === 1 ? '' : 's'}…`;
    } else if (failed) {
        tone = 'bad';
        text = `${failed} change${failed === 1 ? '' : 's'} could not be saved · tap to review`;
    } else if (needSignIn && needSignIn === waiting) {
        tone = 'bad';
        text = `Sign in to send ${needSignIn} saved change${needSignIn === 1 ? '' : 's'}`;
    } else if (waiting) {
        tone = 'sync';
        text = `${waiting} change${waiting === 1 ? '' : 's'} waiting to sync`;
    } else if (flash) {
        tone = 'ok';
        text = flash;
    } else if (copy) {
        tone = 'sync';
        text = `Back online · this is a saved copy from ${formatTime(copy)}. Tap to refresh`;
    }

    clearTimeout(u.hideTimer);

    if (!tone) {
        u.wrap.hidden = true;

        return;
    }

    u.wrap.hidden = false;
    u.pill.dataset.tone = tone;
    u.text.textContent = text;

    if (tone === 'ok') {
        u.hideTimer = setTimeout(() => refreshStatus(), 5000);
    }

    if (!u.panel.hidden) {
        renderPanel();
    }
}

async function renderPanel() {
    const u = buildUi();
    const entries = await Q.all().catch(() => []);
    const copy = offlineCopySavedAt();

    const intro = !isOnline()
        ? `<h3>You're offline</h3><p>You can keep using the portal. Anything you save is kept on this device and sent automatically when you're back online${copy ? `. This page is the copy saved ${escapeHtml(formatTime(copy))}` : ''}.</p>`
        : `<h3>Offline changes</h3><p>${entries.length ? 'Changes made while offline are sent automatically, oldest first.' : 'Everything made offline has been saved.'}</p>`;

    const items = entries
        .map((entry) => {
            const state =
                entry.status === 'failed'
                    ? 'Not saved'
                    : entry.status === 'auth'
                      ? 'Waiting for you to sign in'
                      : entry.status === 'sending'
                        ? 'Sending…'
                        : 'Waiting to sync';

            const errors = entry.errors ? Object.values(entry.errors).flat().slice(0, 3) : [];
            const error = entry.status === 'failed' || entry.status === 'auth' ? errors.join(' ') || entry.lastError || '' : '';

            return `<div class="an-off__item" data-id="${escapeHtml(entry.id)}">
                <b>${escapeHtml(entry.label || 'Change')}</b>
                <small>${escapeHtml(state)} · made ${escapeHtml(formatTime(entry.createdAt))}</small>
                ${error ? `<div class="an-off__err">${escapeHtml(error)}</div>` : ''}
                <div class="an-off__acts">
                    ${entry.status === 'failed' && entry.kind === 'form' && entry.sourceUrl ? '<button type="button" class="an-primary" data-act="fix">Fix and resend</button>' : ''}
                    ${entry.status === 'failed' ? '<button type="button" data-act="retry">Try again</button>' : ''}
                    ${entry.status === 'auth' && settings.startUrl ? `<a class="an-primary" href="${escapeHtml(settings.startUrl)}">Sign in</a>` : ''}
                    <button type="button" data-act="discard">Discard</button>
                </div>
            </div>`;
        })
        .join('');

    const refresh = isOnline() && copy ? '<div class="an-off__acts"><button type="button" class="an-primary" data-act="reload">Load the latest version</button></div>' : '';
    const syncNow = isOnline() && entries.some((e) => e.status !== 'failed') ? '<div class="an-off__acts"><button type="button" class="an-primary" data-act="sync">Sync now</button></div>' : '';

    u.panel.innerHTML = intro + refresh + syncNow + items;

    u.panel.querySelectorAll('[data-act]').forEach((button) => {
        button.addEventListener('click', async (event) => {
            event.stopPropagation();
            const id = button.closest('[data-id]')?.getAttribute('data-id');
            const act = button.getAttribute('data-act');

            if (act === 'reload') {
                window.location.reload();
            } else if (act === 'sync') {
                sync({ announce: true });
            } else if (act === 'retry' && id) {
                await Q.update(id, { status: 'pending', lastError: null, errors: null });
                sync({ announce: true });
            } else if (act === 'discard' && id) {
                if (window.confirm('Discard this change? It has not been saved to the school records and will be lost.')) {
                    await Q.remove(id);
                    refreshStatus();
                }
            } else if (act === 'fix' && id) {
                const entry = await Q.get(id);

                try {
                    window.sessionStorage.setItem('an-offline-restore', id);
                } catch (e) {
                    // Private window: the fields cannot be refilled, but the
                    // person still lands on the right page.
                }

                window.location.href = entry.sourceUrl;
            }
        });
    });
}

/* ---------------------------------------------------------------------- */
/* Keeping writes                                                          */
/* ---------------------------------------------------------------------- */

function formKey(form) {
    if (!form.dataset.offlineKey) {
        form.dataset.offlineKey = Q.uuid();
    }

    let input = form.querySelector('input[name="_offline_key"]');

    if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = '_offline_key';
        form.append(input);
    }

    input.value = form.dataset.offlineKey;

    return form.dataset.offlineKey;
}

function formMethod(form) {
    return (form.getAttribute('method') || 'GET').toUpperCase();
}

/**
 * Keep a form for later. Exposed on window.AkademicNestOffline for the few
 * page scripts that send a form themselves (the document upload).
 */
async function queueForm(form, submitter = null, options = {}) {
    const formData = submitter ? new FormData(form, submitter) : new FormData(form);
    const fields = Q.serializeFormData(formData);
    const url = new URL(form.getAttribute('action') || window.location.href, window.location.href).href;
    const print = Q.fingerprint('POST', url, fields);
    const existing = (await Q.all()).find((e) => e.fingerprint === print && e.status !== 'failed');

    if (existing && !window.confirm('This exact entry is already saved on this device and waiting to sync. Save it again as a second entry?')) {
        return null;
    }

    const entry = await Q.put({
        id: Q.uuid(),
        key: formKey(form),
        kind: 'form',
        scope: settings.offlineScope || null,
        method: 'POST',
        url,
        fields,
        label: options.label || describeForm(form, submitter),
        sourceUrl: window.location.href,
        createdAt: Date.now(),
        status: 'pending',
        attempts: 0,
        fingerprint: print,
    });

    // A fresh key: anything submitted after this is a NEW entry.
    delete form.dataset.offlineKey;
    formKey(form);

    registerBackgroundSync();
    broadcast({ type: 'queue-changed' });

    return entry;
}

function noteOnForm(form, text) {
    form.querySelectorAll(':scope > .an-off-form-note').forEach((el) => el.remove());

    const note = document.createElement('div');
    note.className = 'an-off-form-note';
    note.setAttribute('role', 'status');
    note.textContent = text;
    form.prepend(note);
    note.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    setTimeout(() => note.remove(), 12000);
}

async function forgetThisAccountOnDevice() {
    if (!settings.offlineScope) {
        return;
    }

    await askWorker({ type: 'forget-scope', scope: settings.offlineScope }, 3000);
}

function onSubmit(event) {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || form.dataset.offline === 'skip') {
        return;
    }

    const method = formMethod(form);
    const action = new URL(form.getAttribute('action') || window.location.href, window.location.href);

    if (!sameOrigin(action.href)) {
        return;
    }

    // A search or filter form: the page is opened from the device's copy by
    // the service worker, nothing to keep.
    if (method === 'GET') {
        return;
    }

    // Signing out on purpose removes this account's pages from the device.
    // (The automatic sign-out after three idle minutes does not, and does not
    // run at all while offline: see the idle-session-guard component.)
    if (isLogout(action.pathname)) {
        if (form.dataset.offline !== 'idle-logout' && !event.defaultPrevented) {
            if (!isOnline()) {
                event.preventDefault();
                forgetThisAccountOnDevice().finally(() => {
                    window.location.href = settings.startUrl || '/';
                });
                toast('Signed out on this device. Your saved pages for this account have been removed.', 'offline');

                return;
            }

            event.preventDefault();
            forgetThisAccountOnDevice().finally(() => {
                HTMLFormElement.prototype.submit.call(form);
            });
        }

        return;
    }

    if (event.defaultPrevented) {
        // A page script is sending this itself; its fetch() is covered below.
        return;
    }

    // Keep every write idempotent, online too: a double-tapped Save is done once.
    formKey(form);

    restoredEntryReplaced(form);

    if (isOnline()) {
        return;
    }

    if (isOnlineOnly(action.pathname)) {
        event.preventDefault();

        if (PAYMENT.test(action.pathname)) {
            Q.metaSet('reminder', { url: window.location.href, label: describeForm(form, event.submitter), at: Date.now() }).catch(() => undefined);
            noteOnForm(form, 'Payments need an internet connection, so nothing has been charged. We will remind you as soon as you are back online.');
        } else {
            noteOnForm(form, 'This needs an internet connection (it has to be checked by the server there and then). Please try again when you are back online.');
        }

        return;
    }

    event.preventDefault();
    event.stopImmediatePropagation();

    queueForm(form, event.submitter).then((entry) => {
        if (!entry) {
            return;
        }

        noteOnForm(form, 'Saved on this device. It will be sent to the school automatically when you are back online. You can carry on working.');
        toast('Saved offline. It will sync automatically when you reconnect.', 'offline');
        refreshStatus();
    }, () => {
        noteOnForm(form, 'This could not be saved on the device (storage is full or blocked). Please try again when you are back online.');
    });
}

/**
 * fetch() writes from page scripts. Same-origin and not GET: tried as normal,
 * and if the connection is not there, kept and answered with a 202 saying so.
 */
function installFetchQueue() {
    window.fetch = async function (input, init = {}) {
        const request = input instanceof Request ? input : null;
        const url = request ? request.url : String(input);
        const method = ((init && init.method) || (request && request.method) || 'GET').toUpperCase();
        const headers = new Headers((init && init.headers) || (request && request.headers) || {});

        if (method === 'GET' || method === 'HEAD' || !sameOrigin(url) || headers.has('X-Offline-Bypass') || FETCH_ONLINE_ONLY.test(url)) {
            return nativeFetch(input, init);
        }

        const key = headers.get('X-Offline-Key') || Q.uuid();
        headers.set('X-Offline-Key', key);

        // Captured before sending, because a body can only be read once.
        const captured = await captureBody(request, init);
        const queue = () => keepFetch(url, method, headers, captured, key);

        if (!isOnline()) {
            return queue();
        }

        try {
            const sendInit = request ? undefined : { ...init, headers };

            return await nativeFetch(request ? new Request(request, { headers }) : input, sendInit);
        } catch (error) {
            if (error && error.name === 'AbortError') {
                throw error;
            }

            if (!captured) {
                throw error;
            }

            reachable = false;
            setTimeout(checkReachable, 3000);

            return queue();
        }
    };
}

async function captureBody(request, init) {
    try {
        let body = init && 'body' in init ? init.body : undefined;

        if (body === undefined && request) {
            body = await request.clone().blob();
        }

        if (body === undefined || body === null) {
            return { type: 'none', body: null };
        }

        if (typeof body === 'string') {
            return { type: 'text', body };
        }

        if (body instanceof FormData) {
            return { type: 'form', body: Q.serializeFormData(body) };
        }

        if (body instanceof URLSearchParams) {
            return { type: 'urlencoded', body: body.toString() };
        }

        if (body instanceof Blob) {
            return { type: 'blob', body };
        }

        if (body instanceof ArrayBuffer || ArrayBuffer.isView(body)) {
            return { type: 'blob', body: new Blob([body]) };
        }
    } catch (e) {
        // A stream or something else that cannot be kept: not queued.
    }

    return null;
}

async function keepFetch(url, method, headers, captured, key) {
    if (!captured) {
        throw new TypeError('Offline, and this request cannot be kept for later.');
    }

    const plainHeaders = {};
    headers.forEach((value, name) => {
        if (!/^x-csrf-token$|^x-xsrf-token$|^content-length$/i.test(name)) {
            plainHeaders[name] = value;
        }
    });

    // FormData sets its own boundary when rebuilt.
    if (captured.type === 'form') {
        delete plainHeaders['content-type'];
    }

    await Q.put({
        id: Q.uuid(),
        key,
        kind: 'fetch',
        scope: settings.offlineScope || null,
        method,
        url: new URL(url, window.location.href).href,
        headers: plainHeaders,
        bodyType: captured.type,
        body: captured.body,
        label: `${(document.querySelector('main h1, h1')?.textContent || document.title || 'Change').trim().replace(/\s+/g, ' ').slice(0, 90)} (automatic save)`,
        sourceUrl: window.location.href,
        createdAt: Date.now(),
        status: 'pending',
        attempts: 0,
    });

    registerBackgroundSync();
    broadcast({ type: 'queue-changed' });
    refreshStatus();

    return new Response(
        JSON.stringify({ ok: true, queued: true, offline: true, message: 'Saved on this device. It will be sent when you are back online.' }),
        { status: 202, headers: { 'Content-Type': 'application/json', 'X-Offline-Queued': '1' } },
    );
}

/* ---------------------------------------------------------------------- */
/* Fixing a change the server refused                                      */
/* ---------------------------------------------------------------------- */

let restoring = null;

async function restoreFailedEntry() {
    let id = null;

    try {
        id = window.sessionStorage.getItem('an-offline-restore');
        window.sessionStorage.removeItem('an-offline-restore');
    } catch (e) {
        return;
    }

    if (!id) {
        return;
    }

    const entry = await Q.get(id);

    if (!entry || entry.kind !== 'form') {
        return;
    }

    const form = [...document.querySelectorAll('form')].find(
        (f) => new URL(f.getAttribute('action') || window.location.href, window.location.href).href === entry.url,
    );

    if (!form) {
        toast('That form is not on this page any more. The change is still in the list.', 'bad');

        return;
    }

    const values = new Map();
    entry.fields.forEach(([name, value]) => {
        if (typeof value === 'string') {
            values.set(name, [...(values.get(name) || []), value]);
        }
    });

    values.forEach((list, name) => {
        const fields = form.querySelectorAll(`[name="${CSS.escape(name)}"]`);

        fields.forEach((field, index) => {
            if (field.type === 'checkbox' || field.type === 'radio') {
                field.checked = list.includes(field.value);
            } else if (field.type !== 'file' && field.type !== 'hidden') {
                field.value = list[index] ?? list[0];
                field.dispatchEvent(new Event('input', { bubbles: true }));
                field.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    const errors = entry.errors ? Object.values(entry.errors).flat().join(' ') : entry.lastError;
    noteOnForm(form, `Your offline entry has been filled back in. ${errors ? 'The school system said: ' + errors + ' ' : ''}Correct it and save again.`);
    restoring = { id, form };
}

/** Saving the refilled form replaces the refused entry. */
function restoredEntryReplaced(form) {
    if (restoring && restoring.form === form) {
        Q.remove(restoring.id).catch(() => undefined);
        restoring = null;
    }
}

/* ---------------------------------------------------------------------- */
/* Sync                                                                    */
/* ---------------------------------------------------------------------- */

let channel = null;

function broadcast(message) {
    try {
        channel?.postMessage(message);
    } catch (e) {
        // Nothing listening.
    }
}

async function refreshCsrf(token) {
    if (!token) {
        return;
    }

    document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
    document.querySelectorAll('input[name="_token"]').forEach((input) => {
        input.value = token;
    });
}

async function sync({ announce = false } = {}) {
    if (syncing || !navigator.onLine) {
        return;
    }

    const entries = await Q.all().catch(() => []);

    if (!entries.some((e) => e.status !== 'failed')) {
        return;
    }

    syncing = true;
    refreshStatus();

    let summary = null;

    try {
        summary = await Q.flush({
            idleSeconds: idleSeconds(),
            onResult: () => refreshStatus(),
        });
    } catch (e) {
        summary = null;
    } finally {
        syncing = false;
    }

    if (!summary || summary.busy) {
        refreshStatus();

        return;
    }

    if (summary.offline) {
        reachable = false;
        refreshStatus();

        return;
    }

    reachable = true;
    Q.fetchSession().then((s) => refreshCsrf(s.csrf_token)).catch(() => undefined);

    broadcast({ type: 'queue-changed' });

    if (summary.done) {
        toast(
            `${summary.done} offline change${summary.done === 1 ? ' was' : 's were'} saved to the school system.`,
            'ok',
        );
        refreshStatus('All offline changes synced');
    } else if (summary.failed && announce) {
        toast('Some changes could not be saved. Tap the red notice to review them.', 'bad');
        refreshStatus();
    } else if (summary.auth && !summary.retry) {
        refreshStatus();

        if (announce) {
            toast('Sign in again to send the changes saved on this device.', 'bad');
        }
    } else {
        refreshStatus();
    }
}

function scheduleSync() {
    clearInterval(syncTimer);
    syncTimer = setInterval(async () => {
        const entries = await Q.all().catch(() => []);

        if (entries.some((e) => e.status === 'pending' || e.status === 'sending')) {
            if (!reachable) {
                await checkReachable();
            }

            sync();
        }
    }, SYNC_EVERY_MS);
}

/** navigator.onLine says "online" on a Wi-Fi with no internet behind it. */
async function checkReachable() {
    if (!navigator.onLine) {
        reachable = false;
        refreshStatus();

        return false;
    }

    try {
        const session = await Q.fetchSession();
        const wasUnreachable = !reachable;
        reachable = true;

        if (wasUnreachable || offlineCopySavedAt()) {
            refreshCsrf(session.csrf_token);
        }

        refreshStatus();

        return true;
    } catch (e) {
        reachable = false;
        refreshStatus();

        return false;
    }
}

async function onOnline() {
    if (await checkReachable()) {
        toast('Back online.', 'ok', 2500);
        sync({ announce: true });
        remindPayment();
    }
}

async function remindPayment() {
    const reminder = await Q.metaGet('reminder').catch(() => null);

    if (!reminder) {
        return;
    }

    await Q.metaSet('reminder', null).catch(() => undefined);

    const u = buildUi();
    u.wrap.hidden = false;
    u.pill.dataset.tone = 'sync';
    u.text.textContent = `You're back online. Tap to finish: ${reminder.label}`;
    u.pill.onclick = () => {
        window.location.href = reminder.url;
    };
}

/* ---------------------------------------------------------------------- */
/* Warming                                                                 */
/* ---------------------------------------------------------------------- */

async function warmPortal() {
    if (!settings.offlineScope || !isOnline()) {
        return;
    }

    const connection = navigator.connection;

    if (connection && (connection.saveData || /(^|-)2g$/.test(connection.effectiveType || ''))) {
        // Keep this page only; do not spend a data-saver's allowance.
        tellWorker({ type: 'keep-page', url: window.location.href });

        return;
    }

    const last = await Q.metaGet('warm:' + settings.offlineScope).catch(() => 0);

    // The page that is open is always kept, even if it was loaded before
    // the worker took control.
    tellWorker({ type: 'keep-page', url: window.location.href });

    if (last && Date.now() - last < WARM_EVERY_MS) {
        return;
    }

    const prefixes = settings.offlinePrefixes && settings.offlinePrefixes.length ? settings.offlinePrefixes : ['/'];
    const urls = [settings.homeUrl, settings.startUrl]
        .concat([...document.querySelectorAll('a[href]')].map((a) => a.href))
        .filter((href) => href && sameOrigin(href))
        .map((href) => new URL(href, window.location.href))
        .filter((u) => prefixes.some((p) => u.pathname.startsWith(p)))
        .map((u) => {
            u.hash = '';

            return u.href;
        });

    const never = (settings.offlineRoutes && settings.offlineRoutes.neverWarm) || [];
    const safe = [...new Set(urls)].filter((href) => !matchesAny(routePatterns.neverWarm, new URL(href).pathname));

    tellWorker({ type: 'warm', scope: settings.offlineScope, urls: safe.slice(0, 100), prefixes, never });
}

/* ---------------------------------------------------------------------- */
/* Start                                                                   */
/* ---------------------------------------------------------------------- */

function trackActivity() {
    let stored = 0;
    const note = () => {
        lastActivity = Date.now();

        if (lastActivity - stored > 10000) {
            stored = lastActivity;
            Q.metaSet('lastActivity', lastActivity).catch(() => undefined);
        }
    };

    for (const type of ['keydown', 'pointerdown', 'input', 'change', 'touchstart']) {
        document.addEventListener(type, note, { capture: true, passive: true });
    }

    note();
}

function whenWorkerReady(callback) {
    if (!('serviceWorker' in navigator)) {
        return;
    }

    if (navigator.serviceWorker.controller) {
        callback();
    } else {
        navigator.serviceWorker.addEventListener('controllerchange', () => callback(), { once: true });
    }
}

export default function initOffline() {
    settings = window.AkademicNestPwa || null;

    if (!settings || !Q || !('indexedDB' in window)) {
        return;
    }

    routePatterns = {
        onlineOnly: compilePatterns(settings.offlineRoutes?.onlineOnly),
        logout: compilePatterns(settings.offlineRoutes?.logout),
        neverWarm: compilePatterns(settings.offlineRoutes?.neverWarm),
    };

    window.AkademicNestOffline = {
        queueForm,
        sync: () => sync({ announce: true }),
        isOnline,
        pending: () => Q.all(),
    };

    try {
        channel = 'BroadcastChannel' in window ? new BroadcastChannel('akademicnest-offline') : null;
        channel?.addEventListener('message', () => refreshStatus());
    } catch (e) {
        channel = null;
    }

    installFetchQueue();
    trackActivity();

    // Bubble phase on window: runs after every other submit handler, so a form
    // a page script already took over (defaultPrevented) is left to it.
    window.addEventListener('submit', onSubmit);

    window.addEventListener('online', onOnline);
    window.addEventListener('offline', () => {
        reachable = false;
        toast('You are offline. You can keep working; changes will be saved on this device.', 'offline');
        refreshStatus();
    });

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible' && navigator.onLine) {
            checkReachable().then((ok) => ok && sync());
        }
    });

    navigator.serviceWorker?.addEventListener('message', (event) => {
        const data = event.data || {};

        if (data.type === 'sync-result' || data.type === 'queue-changed') {
            refreshStatus();
        }
    });

    const start = () => {
        buildUi();
        restoreFailedEntry();

        if (!navigator.onLine) {
            reachable = false;
        }

        refreshStatus();

        if (navigator.onLine) {
            checkReachable().then((ok) => {
                if (ok) {
                    sync();
                    remindPayment();
                }
            });
        }

        scheduleSync();

        whenWorkerReady(() => {
            tellWorker({ type: 'hello', scope: settings.offlineScope || null, startUrl: settings.startUrl, homeUrl: settings.homeUrl });
            // After the page has settled, so it never competes with it.
            setTimeout(warmPortal, 2500);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
}
