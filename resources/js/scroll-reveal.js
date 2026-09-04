/**
 * Scroll-driven animation for the public school website.
 *
 * Four jobs, one file, because they all answer the same question - "is this
 * on screen yet?" - and answering it four separate times would mean four
 * observers and four scroll handlers on a page that needs neither.
 *
 *   1. Reveals.    Sections fade and rise as the visitor reaches them.
 *   2. Count-ups.  The statistics count from zero, once, when first seen.
 *   3. Parallax.   A barely-there drift on large background images, desktop only.
 *
 * The NAVBAR's scrolled state is deliberately not here. It already had one, in
 * Alpine on the header itself, and two owners toggling the same appearance
 * from two listeners is how they end up disagreeing. That one was throttled
 * where it stands instead.
 *
 * WHAT THIS DELIBERATELY IS NOT: a JavaScript animation loop. Nothing here
 * animates a property frame by frame except the count-up, which is a number
 * rather than a style. The reveals are CSS transitions that JavaScript only
 * switches on, so the browser composites them off the main thread and a page
 * with forty of them still scrolls at full speed.
 *
 * REDUCED MOTION is checked once, at the top. When it is on, everything is
 * revealed immediately, the counts jump to their final values and no observer
 * or scroll handler is ever created - there is nothing to turn off later
 * because nothing was started.
 */

const REVEALED = 'is-revealed';

const wantsLessMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * Reveal everything at once, for anyone who has asked for less motion - and
 * as the fallback for a browser with no IntersectionObserver, where the
 * alternative would be a page of permanently invisible content.
 */
function revealEverything() {
    document.querySelectorAll('.edn-reveal, .edn-accent').forEach((el) => el.classList.add(REVEALED));

    document.querySelectorAll('[data-count-to]').forEach((el) => {
        el.textContent = formatCount(Number(el.dataset.countTo), el.dataset.countSuffix ?? '');
    });
}

function formatCount(value, suffix) {
    return Math.round(value).toLocaleString() + suffix;
}

/**
 * Count from zero to the target over `duration`, easing out so it slows as it
 * arrives rather than stopping dead.
 *
 * requestAnimationFrame rather than setInterval: the browser skips frames it
 * cannot paint instead of queueing work it will never catch up on, and it
 * pauses entirely when the tab is in the background.
 */
function countUp(el) {
    const target = Number(el.dataset.countTo);
    const suffix = el.dataset.countSuffix ?? '';
    const duration = Number(el.dataset.countDuration ?? 1500);

    if (! Number.isFinite(target)) {
        return;
    }

    const startedAt = performance.now();

    const step = (now) => {
        const elapsed = Math.min((now - startedAt) / duration, 1);
        const eased = 1 - Math.pow(1 - elapsed, 3);

        el.textContent = formatCount(target * eased, suffix);

        if (elapsed < 1) {
            requestAnimationFrame(step);
        }
    };

    requestAnimationFrame(step);
}

function startRevealing() {
    const targets = document.querySelectorAll('.edn-reveal, .edn-accent, [data-count-to]');

    if (targets.length === 0) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (! entry.isIntersecting) {
                    return;
                }

                const el = entry.target;

                el.classList.add(REVEALED);

                if (el.dataset.countTo !== undefined) {
                    countUp(el);
                }

                // ONCE. The spec is explicit that a section must not re-animate
                // every time the visitor nudges the scroll wheel, and a count
                // that restarts on the way back up looks broken.
                observer.unobserve(el);
            });
        },
        {
            // A little way in, so an element reveals as it properly enters the
            // view rather than the instant its top edge clips the bottom.
            rootMargin: '0px 0px -12% 0px',
            threshold: 0.12,
        },
    );

    targets.forEach((el) => {
        // The server renders the REAL figure, so a visitor with no JavaScript
        // reads "1,200" rather than a zero that never moves. That means this
        // has to blank it before the count begins - otherwise the true value
        // shows, then snaps to zero the moment the bar is scrolled to, which
        // looks like a bug rather than an animation.
        if (el.dataset.countTo !== undefined) {
            el.textContent = formatCount(0, el.dataset.countSuffix ?? '');
        }

        observer.observe(el);
    });
}

/**
 * The scroll-linked drift, on one passive handler.
 *
 * Passive, so the browser never waits on this before scrolling, and throttled
 * to one read per frame - a scroll event can fire far more often than the
 * screen refreshes, and doing the work each time is how a smooth page starts
 * to stutter.
 *
 * DEPTH comes from the rates disagreeing. A background is given a small
 * POSITIVE rate, which lags it behind the page - it appears to move upward
 * more slowly than everything around it. The content over it is given a small
 * NEGATIVE rate, so it leads slightly. Neither is large; what the eye reads is
 * not either movement but the difference between them.
 *
 * POSITIONS ARE MEASURED ONCE, not every frame. offsetTop is a layout-reading
 * property: asking for it inside a scroll handler forces the browser to flush
 * layout before it can answer, on every frame, for every layer - the exact
 * "expensive layout recalculation during scrolling" this is supposed to avoid.
 * It cannot change while the page is merely scrolling, so it is cached and
 * re-measured only when the viewport does something that could move it.
 */
function startScrollEffects() {
    const parallaxLayers = Array.from(document.querySelectorAll('.edn-parallax'));

    if (parallaxLayers.length === 0) {
        return;
    }

    let ticking = false;
    let positions = [];
    let strength = 1;

    const measure = () => {
        // Reduced rather than removed on a small screen. A phone has less
        // horsepower and a shorter viewport, so the same travel reads as more
        // movement - but switching the effect off entirely would leave those
        // visitors with the static page this exists to prevent.
        strength = window.innerWidth >= 1024 ? 1 : 0.45;

        positions = parallaxLayers.map((layer) => {
            // Cleared first: a transform left over from the last frame would
            // be measured as part of the layer's position and the drift would
            // creep further from true on every resize.
            layer.style.transform = '';

            const box = layer.getBoundingClientRect();

            // The layer's CENTRE, in DOCUMENT coordinates.
            //
            // THIS IS THE FIX. It was layer.offsetTop, which is measured from
            // the nearest positioned ancestor - and every one of these layers
            // is absolutely positioned inside its own section, so offsetTop
            // was 0, or -50 for the inset backgrounds. Never the distance down
            // the page.
            //
            // The arithmetic then read (scrollY - 0) * rate, which is hundreds
            // of pixels the moment anyone scrolls at all, so every layer was
            // slammed to its cap and pinned there. The transform was being
            // written on every frame and the value never changed: motion that
            // existed in the code and not on the screen.
            return box.top + window.scrollY + box.height / 2;
        });
    };

    const apply = () => {
        // Measured against the middle of the SCREEN rather than its top, so
        // the drift is zero when the section is centred and grows either side
        // of that. A layer therefore moves as its section passes through the
        // viewport - which is the effect - instead of being a function of how
        // far down the document it happens to sit.
        const viewportMiddle = window.scrollY + window.innerHeight / 2;

        parallaxLayers.forEach((layer, index) => {
            const rate = Number(layer.dataset.parallaxRate ?? 0.12) * strength;

            // Per element, because the travel a layer can afford depends on
            // how much room it was given - a background layer inset beyond its
            // section can move further than text that has to stay inside one.
            const max = Number(layer.dataset.parallaxMax ?? 60) * strength;

            const offset = (viewportMiddle - positions[index]) * rate;

            // translate3d keeps it on the compositor.
            layer.style.transform = `translate3d(0, ${Math.max(Math.min(offset, max), -max)}px, 0)`;
        });

        ticking = false;
    };

    const schedule = () => {
        if (! ticking) {
            ticking = true;
            requestAnimationFrame(apply);
        }
    };

    window.addEventListener('scroll', schedule, { passive: true });

    // Re-measure when the viewport changes shape - a resize, a rotation, or a
    // phone's address bar collapsing - because any of those can move a layer
    // in the document without a single scroll event being fired.
    window.addEventListener(
        'resize',
        () => {
            measure();
            schedule();
        },
        { passive: true },
    );

    measure();
    apply();
}

export default function initScrollAnimations() {
    if (wantsLessMotion()) {
        revealEverything();

        return;
    }

    if (! ('IntersectionObserver' in window)) {
        revealEverything();

        return;
    }

    startRevealing();
    startScrollEffects();
}
