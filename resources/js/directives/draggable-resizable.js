const MIN_W = 6;
const MIN_H = 4;

function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
}

/**
 * Maps a resize-handle direction (nw|n|ne|w|e|sw|s|se) to the correct x/y/w/h
 * adjustment, keeping the opposite edge anchored in place.
 */
function applyResize(handle, state, dxPct, dyPct, start) {
    if (handle.includes('e')) {
        state.w = clamp(start.w + dxPct, MIN_W, 100 - start.x);
    }
    if (handle.includes('w')) {
        const w = clamp(start.w - dxPct, MIN_W, start.x + start.w);
        state.x = start.x + (start.w - w);
        state.w = w;
    }
    if (handle.includes('s')) {
        state.h = clamp(start.h + dyPct, MIN_H, 100 - start.y);
    }
    if (handle.includes('n')) {
        const h = clamp(start.h - dyPct, MIN_H, start.y + start.h);
        state.y = start.y + (start.h - h);
        state.h = h;
    }
}

function pointerToPercent(el, event) {
    const rect = el.getBoundingClientRect();

    return {
        x: ((event.clientX - rect.left) / rect.width) * 100,
        y: ((event.clientY - rect.top) / rect.height) * 100,
    };
}

/**
 * `x-draggable-resizable="layout.title"` — makes an element movable by
 * dragging its body, and resizable via its `[data-handle]` children, reading
 * and writing a reactive `{x, y, w, h}` object (percentages of the closest
 * `[data-canvas]` ancestor) directly, so Alpine's reactivity handles
 * the live-preview re-render for free.
 */
export function registerDraggableResizable(Alpine) {
    Alpine.directive('draggable-resizable', (el, { expression }, { evaluateLater, cleanup }) => {
        const container = el.closest('[data-canvas]');

        if (! container) {
            return;
        }

        const getState = evaluateLater(expression);
        let state = null;
        getState((value) => {
            state = value;
        });

        let dragging = false;
        let dragStart = null;
        let dragPointerStart = null;

        const onBodyPointerDown = (event) => {
            if (event.target.closest('[data-handle]') || event.target.isContentEditable) {
                return;
            }

            dragging = true;
            dragStart = { x: state.x, y: state.y, w: state.w, h: state.h };
            dragPointerStart = pointerToPercent(container, event);
            el.setPointerCapture(event.pointerId);
            event.stopPropagation();
        };

        const onBodyPointerMove = (event) => {
            if (! dragging) {
                return;
            }

            const pointer = pointerToPercent(container, event);
            state.x = clamp(dragStart.x + (pointer.x - dragPointerStart.x), 0, 100 - dragStart.w);
            state.y = clamp(dragStart.y + (pointer.y - dragPointerStart.y), 0, 100 - dragStart.h);
        };

        const onBodyPointerUp = () => {
            dragging = false;
        };

        el.addEventListener('pointerdown', onBodyPointerDown);
        el.addEventListener('pointermove', onBodyPointerMove);
        el.addEventListener('pointerup', onBodyPointerUp);
        el.addEventListener('pointercancel', onBodyPointerUp);

        const handleCleanups = [];

        el.querySelectorAll('[data-handle]').forEach((handleEl) => {
            const handle = handleEl.dataset.handle;
            let resizing = false;
            let resizeStart = null;
            let resizePointerStart = null;

            const onHandlePointerDown = (event) => {
                resizing = true;
                resizeStart = { x: state.x, y: state.y, w: state.w, h: state.h };
                resizePointerStart = pointerToPercent(container, event);
                handleEl.setPointerCapture(event.pointerId);
                event.stopPropagation();
            };

            const onHandlePointerMove = (event) => {
                if (! resizing) {
                    return;
                }

                const pointer = pointerToPercent(container, event);
                applyResize(
                    handle,
                    state,
                    pointer.x - resizePointerStart.x,
                    pointer.y - resizePointerStart.y,
                    resizeStart
                );
                event.stopPropagation();
            };

            const onHandlePointerUp = (event) => {
                resizing = false;
                event.stopPropagation();
            };

            handleEl.addEventListener('pointerdown', onHandlePointerDown);
            handleEl.addEventListener('pointermove', onHandlePointerMove);
            handleEl.addEventListener('pointerup', onHandlePointerUp);
            handleEl.addEventListener('pointercancel', onHandlePointerUp);

            handleCleanups.push(() => {
                handleEl.removeEventListener('pointerdown', onHandlePointerDown);
                handleEl.removeEventListener('pointermove', onHandlePointerMove);
                handleEl.removeEventListener('pointerup', onHandlePointerUp);
                handleEl.removeEventListener('pointercancel', onHandlePointerUp);
            });
        });

        cleanup(() => {
            el.removeEventListener('pointerdown', onBodyPointerDown);
            el.removeEventListener('pointermove', onBodyPointerMove);
            el.removeEventListener('pointerup', onBodyPointerUp);
            el.removeEventListener('pointercancel', onBodyPointerUp);
            handleCleanups.forEach((fn) => fn());
        });
    });
}
