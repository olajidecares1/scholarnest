/**
 * A handwritten signature, drawn on a canvas.
 *
 * Three things make the difference between a signature pad that feels like
 * signing and one that feels like MS Paint, and all three are here:
 *
 *   1. The canvas backing store is sized to the device's pixel ratio, so a
 *      stroke on a phone is sharp rather than a stack of fat grey squares.
 *   2. Strokes are drawn as quadratic curves through the midpoints of the last
 *      three points, not as line segments between raw samples. Pointer events
 *      arrive at whatever rate the hardware reports; joining them with
 *      straight lines is what makes a signature look like a seismograph.
 *   3. Line width varies with speed - a fast stroke thins, a slow one
 *      thickens - which is what a pen actually does and what the eye reads as
 *      handwriting.
 *
 * Pointer events cover mouse, touch and stylus in one code path, so a stylus
 * is not a special case that nobody tested.
 */
import { keepSessionAlive, SIGNED_OUT_MESSAGE } from './session-keep-alive';

export default function signaturePad({ action, destroyAction, current }) {
    return {
        /** Where to sign in again, when the session ended while drawing. */
        signInUrl: null,

        showing: false,
        saving: false,
        hasDrawing: false,
        current,
        message: '',
        modalError: '',
        failed: false,

        // Drawing state, kept off the reactive object's hot path.
        ctx: null,
        drawing: false,
        points: [],
        lastWidth: 2,

        open() {
            this.modalError = '';
            this.signInUrl = null;
            this.showing = true;

            // Opening the pad is activity; drawing is too (pointer events are
            // counted by session-keep-alive.js).
            keepSessionAlive();

            // The canvas has no size until it is on screen, so it can only be
            // set up after the modal renders.
            this.$nextTick(() => this.mount());
        },

        close() {
            this.showing = false;
            this.drawing = false;
        },

        mount() {
            const canvas = this.$refs.canvas;

            if (! canvas) {
                return;
            }

            const ratio = Math.max(window.devicePixelRatio || 1, 1);
            const rect = canvas.getBoundingClientRect();

            canvas.width = Math.round(rect.width * ratio);
            canvas.height = Math.round(rect.height * ratio);

            const ctx = canvas.getContext('2d');
            ctx.scale(ratio, ratio);
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
            ctx.strokeStyle = '#111827';

            this.ctx = ctx;
            this.clear();

            // Bound once and removed on close, so a modal opened repeatedly
            // does not stack listeners.
            canvas.onpointerdown = (event) => this.start(event);
            canvas.onpointermove = (event) => this.move(event);
            canvas.onpointerup = (event) => this.end(event);
            canvas.onpointercancel = (event) => this.end(event);
            canvas.onpointerleave = (event) => this.end(event);
        },

        clear() {
            const canvas = this.$refs.canvas;

            if (! canvas || ! this.ctx) {
                return;
            }

            // Cleared to TRANSPARENT rather than painted white: the signature
            // has to sit on a letterhead, a ruled line or a coloured panel
            // without carrying a white box around with it.
            this.ctx.clearRect(0, 0, canvas.width, canvas.height);
            this.hasDrawing = false;
            this.points = [];
            this.modalError = '';
        },

        pointFrom(event) {
            const rect = this.$refs.canvas.getBoundingClientRect();

            return {
                x: event.clientX - rect.left,
                y: event.clientY - rect.top,
                time: performance.now(),
            };
        },

        start(event) {
            event.preventDefault();
            this.$refs.canvas.setPointerCapture?.(event.pointerId);

            this.drawing = true;
            this.points = [this.pointFrom(event)];
            this.lastWidth = 2;
        },

        move(event) {
            if (! this.drawing) {
                return;
            }

            event.preventDefault();

            this.points.push(this.pointFrom(event));

            if (this.points.length < 3) {
                return;
            }

            const [first, second, third] = this.points.slice(-3);
            const from = { x: (first.x + second.x) / 2, y: (first.y + second.y) / 2 };
            const to = { x: (second.x + third.x) / 2, y: (second.y + third.y) / 2 };

            // Faster strokes thin out, the way a pen lifts. Smoothed against
            // the previous width so the line does not flicker between
            // thicknesses on a jittery pointer.
            const distance = Math.hypot(third.x - second.x, third.y - second.y);
            const elapsed = Math.max(third.time - second.time, 1);
            const speed = distance / elapsed;
            const target = Math.max(1.1, Math.min(3.2, 3.2 - speed * 1.4));
            const width = this.lastWidth * 0.65 + target * 0.35;

            this.ctx.lineWidth = width;
            this.ctx.beginPath();
            this.ctx.moveTo(from.x, from.y);
            this.ctx.quadraticCurveTo(second.x, second.y, to.x, to.y);
            this.ctx.stroke();

            this.lastWidth = width;
            this.hasDrawing = true;
        },

        end(event) {
            if (! this.drawing) {
                return;
            }

            // A tap that never moved is still a mark - a full stop, a dot on
            // an "i" - so it gets a dot rather than nothing.
            if (this.points.length === 1) {
                const [point] = this.points;
                this.ctx.beginPath();
                this.ctx.arc(point.x, point.y, 1.4, 0, Math.PI * 2);
                this.ctx.fillStyle = '#111827';
                this.ctx.fill();
                this.hasDrawing = true;
            }

            this.drawing = false;
            this.points = [];
            this.$refs.canvas.releasePointerCapture?.(event.pointerId);
        },

        async save() {
            if (! this.hasDrawing || this.saving) {
                return;
            }

            this.saving = true;
            this.modalError = '';
            this.signInUrl = null;

            try {
                const response = await fetch(action, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        // No signer id, here or anywhere: whose signature this
                        // is, is decided by the session on the server.
                        signature: this.$refs.canvas.toDataURL('image/png'),
                    }),
                });

                const payload = await response.json().catch(() => ({}));

                // Signed out while drawing: say so, and offer the way back.
                // The drawing stays on the pad in case they sign in in
                // another tab and come back to save it.
                if (response.status === 401) {
                    this.signInUrl = payload.redirect || window.location.href;
                    this.modalError = SIGNED_OUT_MESSAGE;

                    return;
                }

                if (response.status === 419) {
                    this.modalError = 'This page was open too long and has expired. Reload the page, then draw your signature again.';

                    return;
                }

                if (! response.ok) {
                    this.modalError = payload.message ?? 'Your signature could not be saved. Please try again.';

                    return;
                }

                this.current = payload.signature_url;
                this.failed = false;
                this.message = payload.message ?? 'Your signature was registered.';
                this.close();
            } catch (error) {
                this.modalError = 'Your signature could not be saved. Please check your connection and try again.';
            } finally {
                this.saving = false;
            }
        },

        async withdraw() {
            if (! destroyAction || ! window.confirm('Remove your registered signature? Documents generated from now on will show a blank line.')) {
                return;
            }

            try {
                const response = await fetch(destroyAction, {
                    method: 'DELETE',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const payload = await response.json().catch(() => ({}));

                this.failed = ! response.ok;
                this.message = payload.message ?? (response.ok ? 'Your signature was removed.' : 'That could not be removed.');

                if (response.ok) {
                    this.current = null;
                }
            } catch (error) {
                this.failed = true;
                this.message = 'That could not be removed. Please check your connection and try again.';
            }
        },
    };
}
