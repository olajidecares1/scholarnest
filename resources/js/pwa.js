/**
 * Installing a school portal.
 *
 * Two jobs: register the service worker (without one a browser will not offer
 * to install anything), and turn the browser's install offer into a prompt
 * somebody actually notices.
 *
 * The browser fires `beforeinstallprompt` once, silently, and then does
 * nothing visible unless the page asks. Left alone, the offer is a small icon
 * in the address bar that almost nobody presses, which for a parent who has
 * been told "install the school app" is the same as the feature not existing.
 * So the event is captured and shown as a banner instead.
 *
 * iOS fires no such event and never will. Safari installs only through
 * Share -> Add to Home Screen, so there the banner explains that instead of
 * offering a button that could not work.
 */

const config = () => window.AkademicNestPwa || null

/** Already running as an installed app, on any platform. */
const isStandalone = () =>
    window.matchMedia('(display-mode: standalone)').matches ||
    window.matchMedia('(display-mode: minimal-ui)').matches ||
    // Safari's own, non-standard flag, the only signal iOS gives.
    window.navigator.standalone === true

const isIos = () =>
    /iphone|ipad|ipod/i.test(window.navigator.userAgent) ||
    // iPadOS reports itself as a Mac; the touch points give it away.
    (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1)

const isSafari = () =>
    /^((?!chrome|android|crios|fxios|edgios).)*safari/i.test(window.navigator.userAgent)

/**
 * Whether this browser was told not to ask again.
 *
 * Storage can throw outright in a private window, and a portal that fails to
 * render because it could not read a dismissal flag would be a poor trade.
 */
const wasDismissed = (key) => {
    try {
        return window.localStorage.getItem(key) === '1'
    } catch (e) {
        return false
    }
}

const remember = (key) => {
    try {
        window.localStorage.setItem(key, '1')
    } catch (e) {
        // Nothing to do. The prompt reappears next visit, which is a smaller
        // problem than throwing here.
    }
}

/**
 * Register the worker.
 *
 * Deliberately not awaited by anything: a failed registration must never keep
 * a portal from rendering. It also cannot succeed outside a secure context,
 * which is why nothing appears when the app is served over plain http on a
 * LAN address.
 */
function registerServiceWorker(url) {
    if (!('serviceWorker' in navigator) || !window.isSecureContext) {
        return
    }

    window.addEventListener('load', () => {
        navigator.serviceWorker.register(url, { scope: '/' }).catch(() => {
            // A worker that will not register costs the install prompt and
            // nothing else. The portal itself is unaffected.
        })
    })
}

/**
 * The banner. Built here rather than in Blade so it can be shown from the
 * event, which arrives long after the page has rendered.
 */
function buildBanner({ title, body, actionLabel, onAction, onDismiss }) {
    const wrap = document.createElement('div')
    wrap.className = 'pwa-install-banner'
    wrap.setAttribute('role', 'dialog')
    wrap.setAttribute('aria-label', 'Install this portal')

    const text = document.createElement('div')
    text.className = 'pwa-install-banner__text'

    const heading = document.createElement('p')
    heading.className = 'pwa-install-banner__title'
    heading.textContent = title

    const detail = document.createElement('p')
    detail.className = 'pwa-install-banner__body'
    detail.textContent = body

    text.append(heading, detail)

    const actions = document.createElement('div')
    actions.className = 'pwa-install-banner__actions'

    if (onAction) {
        const install = document.createElement('button')
        install.type = 'button'
        install.className = 'pwa-install-banner__install'
        install.textContent = actionLabel
        install.addEventListener('click', () => onAction(wrap))
        actions.append(install)
    }

    const dismiss = document.createElement('button')
    dismiss.type = 'button'
    dismiss.className = 'pwa-install-banner__dismiss'
    dismiss.setAttribute('aria-label', 'Not now')
    dismiss.textContent = onAction ? 'Not now' : 'Got it'
    dismiss.addEventListener('click', () => {
        onDismiss()
        wrap.remove()
    })
    actions.append(dismiss)

    wrap.append(text, actions)
    document.body.append(wrap)

    return wrap
}

export default function initPortalInstall() {
    const settings = config()

    if (!settings) {
        return
    }

    registerServiceWorker(settings.serviceWorker)

    // Already installed: there is nothing to offer, and offering anyway is how
    // an app ends up nagging the people who did what it asked.
    if (isStandalone() || wasDismissed(settings.dismissKey)) {
        return
    }

    // ---------------------------------------------------------------
    // Chrome, Edge, Samsung Internet, and Android generally
    // ---------------------------------------------------------------
    window.addEventListener('beforeinstallprompt', (event) => {
        // Stops the browser's own minimal offer, so there is one prompt
        // rather than two saying different things.
        event.preventDefault()

        if (isStandalone() || wasDismissed(settings.dismissKey)) {
            return
        }

        buildBanner({
            title: `Install ${settings.school}`,
            body: `Add the ${settings.portalLabel} to your home screen. It opens straight to your school.`,
            actionLabel: 'Install',
            onAction: async (banner) => {
                banner.remove()
                event.prompt()

                const choice = await event.userChoice.catch(() => null)

                // "dismissed" is not "never", they may install later from the
                // browser menu, but asking again on the next page load would
                // be pestering.
                if (choice && choice.outcome === 'dismissed') {
                    remember(settings.dismissKey)
                }
            },
            onDismiss: () => remember(settings.dismissKey),
        })
    })

    // ---------------------------------------------------------------
    // iOS, where there is no event and no button that can work
    // ---------------------------------------------------------------
    if (isIos() && isSafari()) {
        // Late, so it does not land on top of a page still arriving, and so it
        // never competes with the sign-in form for a first-time visitor's
        // attention.
        window.setTimeout(() => {
            if (isStandalone() || wasDismissed(settings.dismissKey)) {
                return
            }

            buildBanner({
                title: `Install ${settings.school}`,
                body: `Tap Share, then "Add to Home Screen", to open the ${settings.portalLabel} straight from your phone.`,
                actionLabel: null,
                onAction: null,
                onDismiss: () => remember(settings.dismissKey),
            })
        }, 2500)
    }

    // Nothing to keep offering once it is done.
    window.addEventListener('appinstalled', () => {
        remember(settings.dismissKey)
        document.querySelector('.pwa-install-banner')?.remove()
    })
}
