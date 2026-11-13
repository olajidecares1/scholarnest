/**
 * Content slides in, on every page of the platform.
 *
 * Cards, headings, text and form fields fade in while rising a few pixels,
 * one after another: what is on screen when a page opens arrives in a short
 * cascade, and what is further down arrives as it is scrolled into view. The
 * School Admin dashboard, the AkademicNest Team panel, the staff, pupil and
 * parent portals, sign-in pages and the rest all move the same way, without a
 * class being added to any of their views.
 *
 * WHAT IS LEFT ALONE
 *   - Anything that already animates (the registration wizard's wz-* classes,
 *     the public website's scroll reveals, a spinner): one animation per
 *     element, never two fighting.
 *   - Navigation, headers, sidebars, dialogs, dropdowns and anything fixed or
 *     absolutely positioned: furniture that should feel instant, and overlays
 *     that have their own transitions.
 *   - Table rows and cells: the table moves as one block, a thousand rows
 *     cascading would be noise, and slow.
 *   - Print views, and visitors who have asked their system for less motion.
 *
 * WHY JAVASCRIPT HERE: the hiding is done by this script, so if it never runs
 * nothing is hidden; the page is simply static. Each element's own classes
 * and inline styles are restored the moment its animation ends, so hover
 * effects, transforms and transitions behave exactly as before afterwards.
 */

const PRE = 'an-motion-pre';
const IN = 'an-motion-in';

// The kinds of thing that slide in: cards and panels, text, and fields.
const TARGETS = [
    // Cards and panels, written in the platform's utility classes.
    'div[class*="rounded"][class*="border"]',
    'div[class*="rounded"][class*="shadow"]',
    'section[class*="rounded"]',
    'article',
    'form[class*="rounded"]',
    'dl[class*="rounded"]',
    // Text.
    'h1', 'h2', 'h3', 'h4',
    'p',
    'small.block',
    'label.field-label',
    // Fields and the buttons under forms.
    'input:not([type="hidden"]):not([type="checkbox"]):not([type="radio"])',
    'select',
    'textarea',
    'form button[type="submit"]',
].join(',');

// Never animated, and nothing inside them is.
const SKIP_INSIDE = [
    'header', 'nav', 'aside', 'footer',
    'table', 'thead', 'tbody', 'tr',
    '[role="dialog"]', '[role="menu"]', '[role="tooltip"]', '[role="alert"]',
    '[x-cloak]', 'template',
    '[data-no-motion]',
    '.edn-reveal', '.edn-reveal-left', '.edn-reveal-right', '.edn-reveal-scale', '.edn-enter',
    '.wz-rise', '.wz-stagger', '.wz-shell', '.wz-shell-wide',
    '.apexcharts-canvas',
].join(',');

// The first screenful cascades: at most this many steps, this far apart.
const STEP_MS = 45;
const MAX_STEPS = 14;

function wantsLessMotion() {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches
        || window.matchMedia('print').matches;
}

function eligible(el) {
    // Not rendered (inside something hidden), or already excluded.
    if (el.offsetParent === null || el.closest(SKIP_INSIDE)) {
        return false;
    }

    const style = window.getComputedStyle(el);

    if (style.display === 'none' || style.visibility === 'hidden') {
        return false;
    }

    // Already animating, or an overlay of some kind.
    if (style.animationName && style.animationName !== 'none') {
        return false;
    }

    if (style.position === 'fixed' || style.position === 'absolute' || style.position === 'sticky') {
        return false;
    }

    // Anything inside a positioned overlay (a dropdown panel, a popover).
    for (let parent = el.parentElement; parent && parent !== document.body; parent = parent.parentElement) {
        const position = window.getComputedStyle(parent).position;

        if (position === 'fixed' || position === 'absolute') {
            return false;
        }
    }

    return true;
}

function play(el, delay) {
    el.style.setProperty('--an-motion-delay', `${delay}ms`);
    el.classList.add(IN);
    el.classList.remove(PRE);

    const done = () => {
        el.classList.remove(IN);
        el.style.removeProperty('--an-motion-delay');
        el.removeEventListener('animationend', onEnd);
    };

    // Its own animation, not a child's bubbling up.
    const onEnd = (event) => {
        if (event.target === el) {
            done();
        }
    };

    el.addEventListener('animationend', onEnd);
    // Belt and braces: never leave an element mid-animation.
    window.setTimeout(done, delay + 1200);
}

export default function installPageMotion() {
    if (wantsLessMotion() || !('IntersectionObserver' in window)) {
        return;
    }

    // A cap, so a very long page never spends long working out what to move.
    const elements = Array.from(document.body.querySelectorAll(TARGETS)).slice(0, 600).filter(eligible);

    if (elements.length === 0) {
        return;
    }

    const viewportBottom = window.innerHeight;
    let step = 0;
    const later = [];

    // Hide and cascade in one pass, before the first paint, so nothing flashes.
    elements.forEach((el) => {
        const top = el.getBoundingClientRect().top;

        if (top < viewportBottom) {
            const delay = Math.min(step, MAX_STEPS) * STEP_MS;
            step++;
            el.classList.add(PRE);
            requestAnimationFrame(() => play(el, delay));
        } else {
            el.classList.add(PRE);
            later.push(el);
        }
    });

    if (later.length === 0) {
        return;
    }

    // Further down: each batch that scrolls into view cascades on its own.
    const observer = new IntersectionObserver((entries) => {
        let batch = 0;

        entries
            .filter((entry) => entry.isIntersecting)
            .forEach((entry) => {
                observer.unobserve(entry.target);
                play(entry.target, Math.min(batch++, 8) * STEP_MS);
            });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.01 });

    later.forEach((el) => observer.observe(el));

    // Content that never scrolls into view (a short window, printing) must
    // not stay hidden for ever.
    window.addEventListener('beforeprint', () => {
        later.forEach((el) => el.classList.remove(PRE));
    });
}
