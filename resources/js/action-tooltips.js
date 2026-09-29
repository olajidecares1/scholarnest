/*
 * Tooltips for the icon action buttons.
 *
 * Any element with a data-tooltip attribute gets one: on mouse hover (after a
 * short pause, so sweeping across a row of icons does not flicker), at once on
 * keyboard focus, and on a long press on a touch screen, where there is no
 * hover. The text is read at the moment it is shown, so an Alpine binding
 * (:data-tooltip="active ? 'Deactivate' : 'Activate'") is always current.
 *
 * One element on the body, positioned with fixed coordinates, so the
 * overflow-x-auto wrapper around every table cannot clip it. It sits above the
 * button, or below it when there is no room above, and is kept on screen.
 */

const SHOW_DELAY = 140;
const LONG_PRESS = 380;
const TOUCH_LINGER = 1400;
const GAP = 8;
const EDGE = 8;

export function installActionTooltips() {
    if (typeof document === 'undefined' || window.__actionTooltips) {
        return;
    }
    window.__actionTooltips = true;

    let tip = null;
    let current = null;
    let showTimer = null;
    let hideTimer = null;

    const ensureTip = () => {
        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'action-tooltip';
            tip.id = 'action-tooltip';
            tip.setAttribute('role', 'tooltip');
            tip.dataset.placement = 'top';
            document.body.appendChild(tip);
        }

        return tip;
    };

    const targetOf = (node) => (node instanceof Element ? node.closest('[data-tooltip]') : null);

    const place = (el) => {
        const t = ensureTip();
        const r = el.getBoundingClientRect();
        const w = t.offsetWidth;
        const h = t.offsetHeight;
        const vw = document.documentElement.clientWidth;

        let placement = 'top';
        let y = r.top - h - GAP;
        if (y < EDGE) {
            placement = 'bottom';
            y = r.bottom + GAP;
        }

        const centre = r.left + r.width / 2;
        let x = centre - w / 2;
        x = Math.max(EDGE, Math.min(x, vw - w - EDGE));

        const arrow = Math.max(10, Math.min(centre - x, w - 10));

        t.dataset.placement = placement;
        t.style.setProperty('--tt-x', `${Math.round(x)}px`);
        t.style.setProperty('--tt-y', `${Math.round(y)}px`);
        t.style.setProperty('--tt-arrow', `${Math.round(arrow)}px`);
    };

    const show = (el) => {
        const text = (el.getAttribute('data-tooltip') || '').trim();
        if (!text || !el.isConnected) {
            return;
        }

        clearTimeout(hideTimer);
        const t = ensureTip();
        t.textContent = text;
        current = el;
        place(el);
        t.classList.add('is-visible');
    };

    const hide = () => {
        clearTimeout(showTimer);
        clearTimeout(hideTimer);
        current = null;
        tip?.classList.remove('is-visible');
    };

    const schedule = (el, delay) => {
        clearTimeout(showTimer);
        showTimer = setTimeout(() => show(el), delay);
    };

    // Mouse and pen.
    document.addEventListener('pointerover', (event) => {
        if (event.pointerType === 'touch') {
            return;
        }
        const el = targetOf(event.target);
        if (!el || el === current) {
            return;
        }
        schedule(el, current ? 0 : SHOW_DELAY);
    });

    document.addEventListener('pointerout', (event) => {
        if (event.pointerType === 'touch') {
            return;
        }
        const el = targetOf(event.target);
        if (!el) {
            return;
        }
        const next = targetOf(event.relatedTarget);
        if (next === el) {
            return;
        }
        if (!next) {
            hide();
        }
    });

    // Keyboard. focus-visible, so a click with the mouse does not leave one
    // hanging on the button that was pressed.
    document.addEventListener('focusin', (event) => {
        const el = targetOf(event.target);
        if (!el) {
            return;
        }
        let visible = true;
        try {
            visible = el.matches(':focus-visible');
        } catch {
            // Older engines: show it anyway.
        }
        if (visible) {
            show(el);
        }
    });

    document.addEventListener('focusout', (event) => {
        if (targetOf(event.target)) {
            hide();
        }
    });

    // Touch: a long press shows it, and it lingers briefly after the finger
    // lifts so it can be read.
    let pressTimer = null;
    document.addEventListener(
        'touchstart',
        (event) => {
            const el = targetOf(event.target);
            clearTimeout(pressTimer);
            if (!el) {
                if (current) {
                    hide();
                }
                return;
            }
            pressTimer = setTimeout(() => show(el), LONG_PRESS);
        },
        { passive: true },
    );

    const endTouch = () => {
        clearTimeout(pressTimer);
        if (current) {
            clearTimeout(hideTimer);
            hideTimer = setTimeout(hide, TOUCH_LINGER);
        }
    };
    document.addEventListener('touchend', endTouch, { passive: true });
    document.addEventListener('touchcancel', endTouch, { passive: true });
    document.addEventListener('touchmove', () => clearTimeout(pressTimer), { passive: true });

    // Anything that moves the page or acts on the button takes it away.
    document.addEventListener('click', (event) => {
        if (event.detail > 0 && current) {
            hide();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            hide();
        }
    });
    window.addEventListener('scroll', () => current && hide(), { passive: true, capture: true });
    window.addEventListener('resize', () => current && hide(), { passive: true });
}

export default installActionTooltips;
