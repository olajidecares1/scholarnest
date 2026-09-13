/**
 * Using a page counts as activity, not just loading one.
 *
 * School portals sign a person out after three minutes with no request
 * (App\Http\Middleware\LogsOutIdleUsers). "No request" is not the same as "not
 * there": a teacher choosing a question paper on a phone, drawing a signature,
 * or typing a long question makes no request for minutes at a time - and was
 * signed out mid-task, so the upload that followed failed with "error 401" and
 * the work was lost.
 *
 * So a real interaction - a key, a tap, a change to a field - tells the server
 * the person is still here, at most once a minute. A page nobody touches still
 * times out exactly as before: nothing here fires on its own.
 *
 * Only pages that carry <meta name="session-keep-alive"> take part, which is
 * the signed-in portal layouts and nothing public.
 */

const THROTTLE_MS = 60_000;

let lastPing = 0;
let inflight = null;

function endpoint() {
    return document.querySelector('meta[name="session-keep-alive"]')?.content || null;
}

/**
 * Tell the server the person is active, and learn whether they still have a
 * session.
 *
 * @param {{ force?: boolean }} options  force skips the once-a-minute limit -
 *     used right before an upload, where the answer matters.
 * @returns {Promise<{ ok: boolean, expired?: boolean, redirect?: string|null }>}
 *     ok is true when the session is alive OR when the check itself could not
 *     be made (offline): a flaky connection must never block the person.
 */
export async function keepSessionAlive({ force = false } = {}) {
    const url = endpoint();

    if (!url) {
        return { ok: true };
    }

    if (!force && Date.now() - lastPing < THROTTLE_MS) {
        return { ok: true };
    }

    if (inflight) {
        return inflight;
    }

    lastPing = Date.now();

    inflight = fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        cache: 'no-store',
    })
        .then(async (response) => {
            if (response.status === 401 || response.status === 419) {
                const body = await response.json().catch(() => ({}));

                return { ok: false, expired: true, redirect: body.redirect || null };
            }

            return { ok: true };
        })
        .catch(() => ({ ok: true }))
        .finally(() => {
            inflight = null;
        });

    return inflight;
}

/** The sentence shown when an action finds the session already gone. */
export const SIGNED_OUT_MESSAGE = 'You were signed out after a few minutes without activity, so this was not sent. Sign in again, then try once more.';

export function installActivityKeepAlive() {
    if (!endpoint()) {
        return;
    }

    // Deliberate interactions only. Mouse movement and scrolling are not
    // counted: a page left open on a shared computer is still left open.
    for (const type of ['keydown', 'pointerdown', 'input', 'change']) {
        document.addEventListener(type, () => keepSessionAlive(), { capture: true, passive: true });
    }
}
