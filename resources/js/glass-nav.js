// Motion for the floating glass bottom menu (.edn-glass-nav).
//
// The highlight behind the current tab is one sliding pill, not a
// background on each tab. Tapping a tab slides the pill there at once (so
// the tap is answered while the next page loads), the icon gives a small
// bounce, and the tab you came from is remembered for a few seconds; the
// next page then starts the pill on that tab and slides it across to its
// own, so moving between tabs reads as one movement across a page load.
//
// With "reduce motion" on, the pill simply sits on the current tab.
// Without this script, the tab's own tinted background (aria-current, in
// app.css) still marks it.

const KEY = 'edn-glass-nav-from';
const FRESH_FOR_MS = 6000;
// How long the pill takes to slide; must match .edn-glass-pill in app.css.
const SLIDE_MS = 450;

const reduceMotion = () => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

function tabsOf(nav) {
    const grid = nav.firstElementChild;

    return grid ? [...grid.children].filter((el) => el.matches('a, button')) : [];
}

function place(pill, tab, animate, duration = SLIDE_MS) {
    if (!tab) {
        pill.style.opacity = '0';

        return;
    }

    pill.classList.toggle('edn-glass-pill--still', !animate);
    pill.style.transitionDuration = animate ? `${duration}ms` : '';
    pill.style.width = `${tab.offsetWidth}px`;
    pill.style.height = `${tab.offsetHeight}px`;
    pill.style.transform = `translate(${tab.offsetLeft}px, ${tab.offsetTop}px)`;
    pill.style.opacity = '1';
}

function remembered() {
    try {
        const saved = JSON.parse(sessionStorage.getItem(KEY) || 'null');
        sessionStorage.removeItem(KEY);

        return saved && Date.now() - saved.at < FRESH_FOR_MS ? saved : null;
    } catch {
        return null;
    }
}

function remember(nav, fromIndex) {
    try {
        sessionStorage.setItem(KEY, JSON.stringify({ menu: nav.dataset.glassMenu, from: fromIndex, at: Date.now() }));
    } catch {
        // Private browsing without storage: the pill just starts in place.
    }
}

function init(nav) {
    if (nav.dataset.glassReady) {
        return;
    }

    nav.dataset.glassReady = '1';
    nav.dataset.glassMenu ||= tabsOf(nav).map((tab) => tab.getAttribute('href') || '#').join('|');

    const grid = nav.firstElementChild;
    const tabs = tabsOf(nav);
    const pill = document.createElement('span');
    pill.className = 'edn-glass-pill';
    pill.setAttribute('aria-hidden', 'true');
    grid.prepend(pill);
    nav.classList.add('edn-glass-nav--pill');

    const currentIndex = tabs.findIndex((tab) => tab.getAttribute('aria-current') === 'page');
    const current = tabs[currentIndex];
    const saved = remembered();
    const from = saved && saved.menu === nav.dataset.glassMenu ? tabs[saved.from] : null;

    // The slide began when the tab was tapped. If the page arrived before it
    // finished, finish it here, from the tab we came from, in the time left;
    // if it had finished, the pill is simply already in place.
    const left = saved ? SLIDE_MS - (Date.now() - saved.at) : 0;

    if (current && from && from !== current && left > 60 && !reduceMotion()) {
        place(pill, from, false);
        void pill.offsetWidth;
        requestAnimationFrame(() => place(pill, current, true, Math.max(left, 200)));
    } else {
        place(pill, current, false);
    }

    if (current && saved && !reduceMotion()) {
        current.classList.add('edn-glass-tab--arrive');
    }

    const activeColour = current ? getComputedStyle(current).color : null;

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.button > 0) {
                return;
            }

            tab.classList.remove('edn-glass-tab--tap');
            void tab.offsetWidth;
            tab.classList.add('edn-glass-tab--tap');

            // "More" opens a sheet on this page; only a real link moves the pill.
            if (!tab.matches('a[href]') || index === currentIndex) {
                return;
            }

            remember(nav, currentIndex >= 0 ? currentIndex : index);

            if (!reduceMotion()) {
                place(pill, tab, true);

                // The tapped tab takes the active colour now, and the tab
                // being left takes the tapped one's, both fading.
                if (activeColour && current) {
                    current.style.color = getComputedStyle(tab).color;
                    tab.style.color = activeColour;
                }

                tab.classList.add('edn-glass-tab--going');
            }
        });
    });

    // Coming back with the Back button can show the page from the browser's
    // cache with the pill still on the tab that was tapped: put it right.
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) {
            tabs.forEach((tab) => {
                tab.classList.remove('edn-glass-tab--going', 'edn-glass-tab--tap');
                tab.style.color = '';
            });
            place(pill, current, false);
        }
    });

    // Turning the phone changes every tab's width.
    window.addEventListener('resize', () => place(pill, current, false));
}

function initAll() {
    document.querySelectorAll('.edn-glass-nav').forEach(init);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
} else {
    initAll();
}
