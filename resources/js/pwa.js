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
 * Safari on a Mac, which is the desktop half of the same problem as iOS: it
 * fires no beforeinstallprompt either, and installs through File -> Add to
 * Dock instead. Recognised as "Safari, on a Mac, with no touch screen", which
 * is what separates it from an iPad reporting itself as MacIntel.
 */
const isDesktopSafari = () =>
    isSafari() && !isIos() && /macintosh|mac os x/i.test(window.navigator.userAgent)

const isAndroid = () => /android/i.test(window.navigator.userAgent)

/**
 * An app's own built-in browser: Facebook, Instagram, Messenger, TikTok,
 * Snapchat, Telegram, LINE, or a bare Android WebView. This is where most
 * portal links are opened, because schools share them in WhatsApp groups and
 * on social media, and none of these can install anything. Chrome or Safari
 * has to open the page first.
 */
const isInAppBrowser = () => {
    const ua = window.navigator.userAgent

    return (
        /FBAN|FBAV|FB_IAB|FBIOS|Instagram|Messenger|musical_ly|TikTok|BytedanceWebview|Snapchat|Telegram|Line\/|WhatsApp/i.test(ua) ||
        // Android WebView marks itself "; wv)".
        (isAndroid() && /;\s*wv\)/i.test(ua))
    )
}

/**
 * Whether installing here, on Android, produces a REAL APP: one that is in the
 * app drawer, in Settings > Apps, and on the home screen.
 *
 * THIS IS THE BUG SCHOOLS REPORTED. On Android only two browsers do that:
 * Google Chrome (it has Google's WebAPK service mint a small APK) and Samsung
 * Internet. Every other browser, Edge, Opera, Brave, Firefox, Phoenix (the
 * default on Tecno, Infinix and itel phones), Mi Browser, UC, Vivo, HeyTap
 * and the rest, only ever adds a HOME-SCREEN SHORTCUT: an icon with the
 * browser's badge that never appears in the app drawer. Several of them still
 * fire beforeinstallprompt, so the banner offered "Install", the person said
 * yes, and what they got was a shortcut. From their side that is an install
 * that did not work.
 *
 * So off Chrome and Samsung Internet the banner no longer offers an install
 * that cannot deliver an app. It offers to open this same page in Chrome,
 * where it can.
 */
const canInstallRealAndroidApp = () => {
    const ua = window.navigator.userAgent
    const brands = (window.navigator.userAgentData?.brands || []).map((b) => b.brand)

    if (/SamsungBrowser/i.test(ua)) {
        return true
    }

    if (isInAppBrowser() || window.navigator.brave) {
        return false
    }

    // The brand list, where the browser gives one, is the honest answer:
    // Chrome says "Google Chrome", Edge "Microsoft Edge", Opera "Opera", and
    // most rebadged Chromium browsers only "Chromium".
    if (brands.length) {
        return brands.includes('Google Chrome')
    }

    return (
        /Chrome\/\d+/i.test(ua) &&
        !/EdgA|Edg\/|OPR\/|Opera|OPX|OPT\/|Brave|YaBrowser|UCBrowser|UCWEB|MiuiBrowser|XiaoMi|HuaweiBrowser|HeyTapBrowser|VivoBrowser|OppoBrowser|PHX\/|Phoenix|Silk|DuckDuckGo|Firefox|Version\/\d/i.test(ua)
    )
}

/**
 * A link that opens this exact page in Google Chrome on Android.
 *
 * The intent: scheme is how Android hands a URL to one specific app. If Chrome
 * is not on the phone at all, the fallback takes them to it on Play Store
 * rather than leaving the tap doing nothing.
 */
const chromeIntentUrl = () => {
    const here = new URL(window.location.href)
    const fallback = encodeURIComponent('https://play.google.com/store/apps/details?id=com.android.chrome')
    const scheme = here.protocol.replace(':', '')

    return `intent://${here.host}${here.pathname}${here.search}#Intent;scheme=${scheme};package=com.android.chrome;S.browser_fallback_url=${fallback};end`
}

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
function buildBanner({ title, body, actionLabel, actionHref = null, onAction, onDismiss, dismissLabel = null }) {
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

    if (actionHref) {
        // A real link rather than a scripted navigation: an in-app browser
        // is far more willing to hand an intent: link it can see to Android.
        const open = document.createElement('a')
        open.className = 'pwa-install-banner__install'
        open.href = actionHref
        open.rel = 'noopener'
        open.textContent = actionLabel
        actions.append(open)
    } else if (onAction) {
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
    dismiss.textContent = dismissLabel || (onAction || actionHref ? 'Not now' : 'Got it')
    dismiss.addEventListener('click', () => {
        onDismiss()
        wrap.remove()
    })
    actions.append(dismiss)

    wrap.append(text, actions)
    document.body.append(wrap)

    return wrap
}

/** Nothing to offer: already running as an app, or already told to stop. */
const settled = (settings) => isStandalone() || wasDismissed(settings.dismissKey)

/**
 * The one-tap offer, on the browsers that give us an event to fire.
 */
function offerInstall(settings, event) {
    if (settled(settings) || document.querySelector('.pwa-install-banner')) {
        return
    }

    // An Android browser that would only make a shortcut: send them to
    // Chrome instead of letting its install produce something that is not
    // in the app drawer. See canInstallRealAndroidApp().
    if (isAndroid() && !canInstallRealAndroidApp()) {
        offerChrome(settings)

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
}

/**
 * On Android, off Chrome: open this page in Chrome, where installing gives a
 * real app. Nothing is remembered as dismissed when they take the link,
 * because the point is for Chrome to offer the install next.
 */
function offerChrome(settings) {
    if (settled(settings) || document.querySelector('.pwa-install-banner')) {
        return
    }

    buildBanner({
        title: `Install ${settings.school}`,
        body: isInAppBrowser()
            ? `This app's browser can't install the ${settings.portalLabel}. Open it in Chrome, then tap Install, and it will appear with your other apps.`
            : `This browser only adds a shortcut. Open in Chrome and tap Install to get the ${settings.portalLabel} as an app in your app drawer.`,
        actionLabel: 'Open in Chrome',
        actionHref: chromeIntentUrl(),
        onDismiss: () => remember(settings.dismissKey),
    })
}

/**
 * Tell them where the app went. On Android the new icon lands in the app
 * drawer and, depending on the launcher, not always on the home screen, so
 * somebody who looks only at the home screen can believe nothing happened.
 */
function confirmInstalled(settings) {
    document.querySelector('.pwa-install-banner')?.remove()

    const banner = buildBanner({
        title: `${settings.name} is installed`,
        body: 'Find it with your other apps in the app drawer. It opens straight to your school.',
        actionLabel: null,
        onAction: null,
        onDismiss: () => {},
        dismissLabel: 'OK',
    })

    window.setTimeout(() => banner.remove(), 12000)
}

/**
 * The explanation, on the browsers that install only through a menu.
 *
 * Late, so it does not land on top of a page still arriving, and so it never
 * competes with the sign-in form for a first-time visitor's attention.
 */
function explainInstall(settings, body) {
    window.setTimeout(() => {
        if (settled(settings) || document.querySelector('.pwa-install-banner')) {
            return
        }

        buildBanner({
            title: `Install ${settings.school}`,
            body,
            actionLabel: null,
            onAction: null,
            onDismiss: () => remember(settings.dismissKey),
        })
    }, 2500)
}

export default function initPortalInstall() {
    const settings = config()

    if (!settings) {
        return
    }

    registerServiceWorker(settings.serviceWorker)

    // Already installed: there is nothing to offer, and offering anyway is how
    // an app ends up nagging the people who did what it asked.
    if (settled(settings)) {
        return
    }

    // ---------------------------------------------------------------
    // Chrome, Edge, Samsung Internet, Android generally, and the same
    // browsers on a laptop or desktop, where installing puts the portal
    // in the applications list rather than on a home screen.
    // ---------------------------------------------------------------
    //
    // The event may already have fired: this module is deferred, and the
    // page head catches it for us. See resources/views/components/pwa.blade.php.
    if (window.AkademicNestPwaPrompt) {
        offerInstall(settings, window.AkademicNestPwaPrompt)
    }

    window.addEventListener('akademicnest:installable', () => {
        if (window.AkademicNestPwaPrompt) {
            offerInstall(settings, window.AkademicNestPwaPrompt)
        }
    })

    // Belt and braces: if the head script is ever absent, this still works.
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault()
        offerInstall(settings, event)
    })

    // ---------------------------------------------------------------
    // iPhone and iPad, where there is no event and no button that can work
    // ---------------------------------------------------------------
    //
    // Every browser on iOS 16.4 and later can add a web app to the Home
    // Screen, not only Safari, so Chrome, Edge and Firefox users are told too;
    // before, they saw nothing at all. The in-app browsers inside Facebook,
    // Instagram, TikTok and the like cannot, and are sent to Safari.
    //
    // "Open as Web App" is spelled out because iOS 26 shows it as a switch on
    // the Add to Home Screen sheet, and with it off the icon is a bookmark
    // that opens a Safari tab, not an app.
    if (isIos()) {
        if (isInAppBrowser()) {
            explainInstall(
                settings,
                `Open this page in Safari first (tap ••• or the compass icon, then "Open in Safari"). Then tap Share, "Add to Home Screen", and keep "Open as Web App" on.`,
            )
        } else if (isSafari()) {
            explainInstall(
                settings,
                `Tap Share (on newer iPhones it's under •••), then "Add to Home Screen", keep "Open as Web App" on, and tap Add. The ${settings.portalLabel} then opens from your Home Screen like an app.`,
            )
        } else {
            explainInstall(
                settings,
                `Tap the Share icon in the address bar (or ••• then Share), then "Add to Home Screen" and Add. The ${settings.portalLabel} then opens from your Home Screen like an app.`,
            )
        }
    }

    // ---------------------------------------------------------------
    // Android browsers that cannot make a real app, where no event may
    // ever arrive: in-app browsers, and most non-Chrome browsers
    // ---------------------------------------------------------------
    //
    // Late, like explainInstall(), so that where a browser DOES fire the
    // event, offerInstall() gets there first and routes it the same way.
    if (isAndroid() && !canInstallRealAndroidApp()) {
        window.setTimeout(() => offerChrome(settings), 2500)
    }

    // ---------------------------------------------------------------
    // Safari on a Mac, which installs through the File menu
    // ---------------------------------------------------------------
    if (isDesktopSafari()) {
        explainInstall(
            settings,
            `Choose File, then "Add to Dock", to keep the ${settings.portalLabel} one click away.`,
        )
    }

    // Nothing to keep offering once it is done.
    window.addEventListener('appinstalled', () => {
        remember(settings.dismissKey)
        window.AkademicNestPwaPrompt = null
        document.querySelector('.pwa-install-banner')?.remove()

        if (isAndroid()) {
            confirmInstalled(settings)
        }
    })
}
