/*
 * AkademicNest offline queue: the one copy of the code that stores writes made
 * with no connection, and sends them when the connection returns.
 *
 * SHARED BY THE PAGE AND THE SERVICE WORKER. The page imports this file through
 * Vite (resources/js/offline/index.js); the worker has it pasted in by
 * PwaController::serviceWorker(). So it is written as a plain script: no
 * import, no export, no window, no document. It hangs one object on the global
 * scope, AkademicNestOfflineQueue, and both sides use that.
 *
 * WHAT IS STORED. Each entry is one action a person took offline: a form they
 * submitted (every field, files included, as Blobs) or a write a page script
 * made with fetch (a CBT answer, a signature). Each carries:
 *
 *   key    a random idempotency key, sent with the write; the server answers a
 *          second arrival of the same key from its receipt instead of doing
 *          the work twice (App\Http\Middleware\OfflineSupport)
 *   scope  the account that made it (App\Support\OfflineScope); an entry is
 *          only ever sent while that same account is signed in
 *
 * IndexedDB survives closing the app, reloading, and restarting the phone, so
 * nothing queued is lost until the server has confirmed it.
 */
(function (root) {
    'use strict';

    if (root.AkademicNestOfflineQueue) {
        return;
    }

    var DB_NAME = 'akademicnest-offline';
    var DB_VERSION = 1;
    var QUEUE = 'queue';
    var META = 'meta';

    // How long one sender may hold an entry before another may take it over,
    // in case the tab that was sending it was closed mid-request.
    var LEASE_MS = 90 * 1000;

    var dbPromise = null;

    function open() {
        if (dbPromise) {
            return dbPromise;
        }

        dbPromise = new Promise(function (resolve, reject) {
            var request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = function () {
                var db = request.result;

                if (!db.objectStoreNames.contains(QUEUE)) {
                    var store = db.createObjectStore(QUEUE, { keyPath: 'id' });
                    store.createIndex('createdAt', 'createdAt');
                }

                if (!db.objectStoreNames.contains(META)) {
                    db.createObjectStore(META, { keyPath: 'k' });
                }
            };

            request.onsuccess = function () {
                var db = request.result;

                // Another tab upgrading the database closes this connection.
                db.onversionchange = function () {
                    db.close();
                    dbPromise = null;
                };

                resolve(db);
            };

            request.onerror = function () {
                dbPromise = null;
                reject(request.error);
            };
        });

        return dbPromise;
    }

    function tx(storeName, mode, work) {
        return open().then(function (db) {
            return new Promise(function (resolve, reject) {
                var transaction = db.transaction(storeName, mode);
                var store = transaction.objectStore(storeName);
                var out = { value: undefined };

                work(store, out);

                transaction.oncomplete = function () {
                    resolve(out.value);
                };
                transaction.onerror = function () {
                    reject(transaction.error);
                };
                transaction.onabort = function () {
                    reject(transaction.error);
                };
            });
        });
    }

    function uuid() {
        if (root.crypto && typeof root.crypto.randomUUID === 'function') {
            return root.crypto.randomUUID();
        }

        var bytes = new Uint8Array(16);
        root.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;

        var hex = Array.prototype.map.call(bytes, function (b) {
            return ('0' + b.toString(16)).slice(-2);
        }).join('');

        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }

    /* ------------------------------------------------------------------ */
    /* Entries                                                             */
    /* ------------------------------------------------------------------ */

    function put(entry) {
        return tx(QUEUE, 'readwrite', function (store) {
            store.put(entry);
        }).then(function () {
            return entry;
        });
    }

    function get(id) {
        return tx(QUEUE, 'readonly', function (store, out) {
            var request = store.get(id);
            request.onsuccess = function () {
                out.value = request.result || null;
            };
        });
    }

    function all() {
        return tx(QUEUE, 'readonly', function (store, out) {
            var request = store.index('createdAt').getAll();
            request.onsuccess = function () {
                out.value = request.result || [];
            };
        });
    }

    function remove(id) {
        return tx(QUEUE, 'readwrite', function (store) {
            store.delete(id);
        });
    }

    function update(id, patch) {
        return tx(QUEUE, 'readwrite', function (store, out) {
            var request = store.get(id);
            request.onsuccess = function () {
                if (!request.result) {
                    return;
                }

                var next = Object.assign({}, request.result, patch);
                store.put(next);
                out.value = next;
            };
        });
    }

    /**
     * Take an entry for sending. Atomic: inside one readwrite transaction, so
     * two tabs (or a tab and the worker) can never both take the same entry.
     * Null when somebody else holds it or it is not waiting to be sent.
     */
    function lease(id) {
        return tx(QUEUE, 'readwrite', function (store, out) {
            var request = store.get(id);
            request.onsuccess = function () {
                var entry = request.result;
                var now = Date.now();

                if (!entry) {
                    return;
                }

                var waiting = entry.status === 'pending' || entry.status === 'auth' || (entry.status === 'sending' && (entry.leaseUntil || 0) < now);

                if (!waiting) {
                    return;
                }

                entry.status = 'sending';
                entry.leaseUntil = now + LEASE_MS;
                store.put(entry);
                out.value = entry;
            };
        });
    }

    /* ------------------------------------------------------------------ */
    /* Small facts shared between tabs and the worker                      */
    /* ------------------------------------------------------------------ */

    function metaGet(k) {
        return tx(META, 'readonly', function (store, out) {
            var request = store.get(k);
            request.onsuccess = function () {
                out.value = request.result ? request.result.v : undefined;
            };
        });
    }

    function metaSet(k, v) {
        return tx(META, 'readwrite', function (store) {
            store.put({ k: k, v: v });
        });
    }

    /* ------------------------------------------------------------------ */
    /* Forms <-> storable data                                             */
    /* ------------------------------------------------------------------ */

    var DROP_FIELDS = { _token: true, _offline_key: true };

    /**
     * FormData as a list of pairs IndexedDB can hold. A file is kept as its
     * Blob plus its name, which is what lets a photo chosen offline still be
     * uploaded later.
     */
    function serializeFormData(formData) {
        var pairs = [];

        formData.forEach(function (value, name) {
            if (DROP_FIELDS[name]) {
                return;
            }

            if (typeof value === 'string') {
                pairs.push([name, value]);
            } else if (value && typeof value === 'object') {
                // An empty file input submits a nameless empty file.
                if (!value.size && !value.name) {
                    return;
                }

                pairs.push([name, { __file: true, blob: value, name: value.name || 'upload', type: value.type || '' }]);
            }
        });

        return pairs;
    }

    function buildFormData(pairs) {
        var formData = new FormData();

        (pairs || []).forEach(function (pair) {
            var value = pair[1];

            if (value && value.__file) {
                formData.append(pair[0], value.blob, value.name);
            } else {
                formData.append(pair[0], value);
            }
        });

        return formData;
    }

    /** A stable fingerprint of what was entered, to catch an accidental second tap. */
    function fingerprint(method, url, pairs) {
        var text = method + ' ' + url + ' ' + (pairs || []).map(function (pair) {
            var value = pair[1];

            return pair[0] + '=' + (value && value.__file ? 'file:' + value.name + ':' + (value.blob && value.blob.size) : value);
        }).join('&');

        var hash = 5381;

        for (var i = 0; i < text.length; i++) {
            hash = ((hash << 5) + hash + text.charCodeAt(i)) | 0;
        }

        return String(hash >>> 0);
    }

    /* ------------------------------------------------------------------ */
    /* Sending                                                             */
    /* ------------------------------------------------------------------ */

    var SESSION_URL = '/offline/session';

    function fetchSession() {
        return fetch(SESSION_URL, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json', 'X-Offline-Bypass': '1' },
        }).then(function (response) {
            if (!response.ok) {
                throw new Error('session ' + response.status);
            }

            return response.json();
        });
    }

    function readJson(response) {
        var type = response.headers.get('Content-Type') || '';

        if (type.indexOf('json') === -1) {
            return Promise.resolve({});
        }

        return response.json().catch(function () {
            return {};
        });
    }

    /**
     * Send one entry. Resolves to what happened:
     *   done     the server has it (or already had it)
     *   retry    could not be sent now, try again later
     *   auth     the account is not signed in; wait for a sign-in
     *   failed   the server refused it (shown to the person to fix or discard)
     */
    function replay(entry, context) {
        var headers = {
            Accept: 'application/json',
            'X-Offline-Replay': '1',
            'X-Offline-Key': entry.key,
            'X-Offline-Bypass': '1',
            'X-CSRF-TOKEN': context.token || '',
        };

        if (typeof context.idleSeconds === 'number' && context.idleSeconds >= 0) {
            headers['X-Offline-Resume'] = String(Math.floor(context.idleSeconds));
        }

        var init = { method: 'POST', credentials: 'same-origin', headers: headers, redirect: 'follow' };

        if (entry.kind === 'fetch') {
            init.method = entry.method || 'POST';
            delete headers['X-Offline-Replay'];
            Object.keys(entry.headers || {}).forEach(function (name) {
                if (!/^x-csrf-token$|^x-xsrf-token$|^content-length$/i.test(name)) {
                    headers[name] = entry.headers[name];
                }
            });

            if (entry.bodyType === 'form') {
                init.body = buildFormData(entry.body);
            } else if (entry.bodyType === 'urlencoded') {
                init.body = new URLSearchParams(entry.body);
            } else if (entry.body !== undefined && entry.body !== null) {
                init.body = entry.body;
            }
        } else {
            var formData = buildFormData(entry.fields);
            formData.set('_token', context.token || '');
            formData.set('_offline_key', entry.key);
            init.body = formData;
        }

        return fetch(entry.url, init).then(
            function (response) {
                var status = response.status;

                return readJson(response).then(function (body) {
                    var message = body && body.message ? String(body.message) : null;

                    if (status >= 200 && status < 300) {
                        if (body && body.ok === false) {
                            return { outcome: 'failed', message: message || 'Not accepted.', errors: body.errors || null };
                        }

                        // A sign-in page answering a replay means the session had gone.
                        if (response.redirected && /login|sign-in|signin/i.test(response.url)) {
                            return { outcome: 'auth', message: 'Sign in again to send this.' };
                        }

                        return { outcome: 'done', message: message, redirect: body && body.redirect, duplicate: !!(body && body.duplicate) };
                    }

                    if (status === 401) {
                        return { outcome: 'auth', message: 'Sign in again to send this.' };
                    }

                    if (status === 419) {
                        return { outcome: 'stale-token', message: message };
                    }

                    if (status === 409 && body && body.in_progress) {
                        return { outcome: 'retry', message: 'Already being saved.' };
                    }

                    if (status === 422) {
                        return { outcome: 'failed', message: message || 'Some details need correcting.', errors: body.errors || null };
                    }

                    if (status === 408 || status === 425 || status === 429 || status >= 500) {
                        return { outcome: 'retry', message: message || 'The server is busy (' + status + ').' };
                    }

                    return { outcome: 'failed', message: message || 'The server refused this (' + status + ').' };
                });
            },
            function () {
                return { outcome: 'retry', message: 'No connection.' };
            },
        );
    }

    /**
     * Send everything that can be sent now, oldest first.
     *
     * options.idleSeconds  how long since the person last touched the app
     * options.onResult     called with (entry, result) after each one
     *
     * Resolves to a summary: { done, failed, auth, retry, remaining }.
     */
    function flush(options) {
        options = options || {};

        var run = function () {
            return fetchSession().then(
                function (session) {
                    return sendAll(session, options);
                },
                function () {
                    return all().then(function (entries) {
                        return { done: 0, failed: 0, auth: 0, retry: entries.length, remaining: entries.length, offline: true };
                    });
                },
            );
        };

        // One sender at a time across every tab and the worker, where the
        // browser can say so. The lease in each entry is the fallback.
        if (root.navigator && root.navigator.locks && typeof root.navigator.locks.request === 'function') {
            return root.navigator.locks.request('akademicnest-offline-sync', { ifAvailable: true }, function (lock) {
                if (!lock) {
                    return { busy: true };
                }

                return run();
            });
        }

        return run();
    }

    function sendAll(session, options) {
        var summary = { done: 0, failed: 0, auth: 0, retry: 0, remaining: 0 };
        var active = session.scopes || [];
        var token = session.csrf_token;

        return all().then(function (entries) {
            var chain = Promise.resolve();
            var stop = false;

            entries.forEach(function (entry) {
                chain = chain.then(function () {
                    if (entry.status === 'failed') {
                        summary.failed++;
                        return;
                    }

                    if (stop) {
                        summary.retry++;
                        return;
                    }

                    // Only ever sent for the account that made it.
                    if (entry.scope && active.indexOf(entry.scope) === -1) {
                        summary.auth++;

                        return entry.status === 'auth' ? undefined : update(entry.id, { status: 'auth', lastError: 'Sign in again to send this.' });
                    }

                    return lease(entry.id).then(function (taken) {
                        if (!taken) {
                            return;
                        }

                        var attempt = function (currentToken, retried) {
                            return replay(taken, { token: currentToken, idleSeconds: options.idleSeconds }).then(function (result) {
                                if (result.outcome === 'stale-token' && !retried) {
                                    return fetchSession().then(function (fresh) {
                                        token = fresh.csrf_token;
                                        active = fresh.scopes || active;

                                        return attempt(token, true);
                                    });
                                }

                                if (result.outcome === 'stale-token') {
                                    result = { outcome: 'auth', message: 'Sign in again to send this.' };
                                }

                                return result;
                            });
                        };

                        return attempt(token, false).then(function (result) {
                            var after;

                            if (result.outcome === 'done') {
                                summary.done++;
                                after = remove(taken.id);
                            } else if (result.outcome === 'failed') {
                                summary.failed++;
                                after = update(taken.id, { status: 'failed', lastError: result.message, errors: result.errors || null, attempts: (taken.attempts || 0) + 1, leaseUntil: 0 });
                            } else if (result.outcome === 'auth') {
                                summary.auth++;
                                after = update(taken.id, { status: 'auth', lastError: result.message, leaseUntil: 0 });
                            } else {
                                summary.retry++;
                                // Keep the order: anything after this one waits
                                // for the next attempt rather than overtaking it.
                                stop = true;
                                after = update(taken.id, { status: 'pending', lastError: result.message, attempts: (taken.attempts || 0) + 1, leaseUntil: 0 });
                            }

                            return after.then(function () {
                                if (typeof options.onResult === 'function') {
                                    try {
                                        options.onResult(taken, result);
                                    } catch (e) {
                                        // A display problem must never stop the queue.
                                    }
                                }
                            });
                        });
                    });
                });
            });

            return chain;
        }).then(function () {
            return all().then(function (left) {
                summary.remaining = left.length;

                return summary;
            });
        });
    }

    root.AkademicNestOfflineQueue = {
        open: open,
        uuid: uuid,
        put: put,
        get: get,
        all: all,
        remove: remove,
        update: update,
        lease: lease,
        metaGet: metaGet,
        metaSet: metaSet,
        serializeFormData: serializeFormData,
        buildFormData: buildFormData,
        fingerprint: fingerprint,
        fetchSession: fetchSession,
        replay: replay,
        flush: flush,
        SESSION_URL: SESSION_URL,
    };
})(typeof self !== 'undefined' ? self : globalThis);
