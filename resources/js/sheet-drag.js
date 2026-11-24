// Swipe a bottom sheet down to close it, as on a phone's own sheets.
//
// For the More sheet (.edn-glass-sheet, x-app-more-sheet): dragging its top
// part ([data-sheet-grab]: the handle and title) moves the sheet with the
// finger. Let go past a third of its height, or with a quick flick
// downwards, and it slides away and closes; otherwise it springs back.
// The tiles below scroll as usual: only the top part drags.

const CLOSE_FRACTION = 0.3;
const FLICK_PX_PER_MS = 0.6;

function attach(sheet) {
    const grab = sheet.querySelector('[data-sheet-grab]');

    if (!grab || sheet.dataset.dragReady) {
        return;
    }

    sheet.dataset.dragReady = '1';

    let startY = 0;
    let lastY = 0;
    let lastT = 0;
    let velocity = 0;
    let dragging = false;

    const move = (dy) => {
        sheet.style.transform = `translateY(${Math.max(0, dy)}px)`;
    };

    grab.addEventListener('touchstart', (event) => {
        // The close button and anything typed into work as usual.
        if (event.target.closest('button, input, a')) {
            return;
        }

        dragging = true;
        startY = lastY = event.touches[0].clientY;
        lastT = performance.now();
        velocity = 0;
        sheet.style.transition = 'none';
    }, { passive: true });

    grab.addEventListener('touchmove', (event) => {
        if (!dragging) {
            return;
        }

        const y = event.touches[0].clientY;
        const now = performance.now();
        velocity = (y - lastY) / Math.max(now - lastT, 1);
        lastY = y;
        lastT = now;

        // Pulling up past the top gives a little, then resists.
        const dy = y - startY;
        move(dy > 0 ? dy : 0);
    }, { passive: true });

    const release = () => {
        if (!dragging) {
            return;
        }

        dragging = false;
        const dy = Math.max(0, lastY - startY);
        const closes = dy > sheet.offsetHeight * CLOSE_FRACTION || (velocity > FLICK_PX_PER_MS && dy > 24);

        sheet.style.transition = 'transform 0.32s cubic-bezier(0.2, 0.9, 0.3, 1)';

        if (closes) {
            sheet.style.transform = 'translateY(110%)';
            window.setTimeout(() => {
                sheet.dispatchEvent(new CustomEvent('edn-sheet-close'));
                // Once it is hidden, forget the drag for next time.
                window.setTimeout(() => {
                    sheet.style.transition = '';
                    sheet.style.transform = '';
                }, 320);
            }, 200);
        } else {
            sheet.style.transform = 'translateY(0)';
            window.setTimeout(() => {
                sheet.style.transition = '';
                sheet.style.transform = '';
            }, 340);
        }
    };

    grab.addEventListener('touchend', release);
    grab.addEventListener('touchcancel', release);
}

function init() {
    document.querySelectorAll('.edn-glass-sheet').forEach(attach);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
