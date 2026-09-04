/**
 * A list that rotates itself upward a page at a time, and that a visitor can
 * also scroll by hand.
 *
 * The two halves of that sentence are why this exists rather than the
 * show-one-page-and-hide-the-rest it replaces. Hiding pages needs them
 * absolutely positioned on top of one another and the panel clipped, and a
 * clipped panel cannot be scrolled - so "let people scroll it" and "swap pages
 * in and out" could not both be true. Here every page sits in normal flow
 * inside a real scrolling box, and the rotation moves the scroll position
 * instead of moving the pages.
 *
 * The movement is animated by hand rather than with scroll-behavior: smooth,
 * because the browser picks that duration and will not be told otherwise. The
 * whole point of these panels is a slow, deliberate three-second rise, so the
 * scroll position is eased across exactly that long on a cubic ease-out - the
 * same curve, direction and duration the CSS transition used.
 *
 * It moves to a page's own offsetTop rather than by a fixed step, so pages of
 * unequal height - a final group of one event where the last held three - land
 * flush instead of drifting further out of true with every turn.
 *
 * It yields to the visitor. Hovering, touching or scrolling by hand pauses the
 * timer, because a panel that pulls itself away from somebody who is reading
 * it is worse than one that never moved at all.
 */
export default function marqueeList({ dwell = 30000, duration = 3000 } = {}) {
    return {
        /** Which page is showing. Bound to the dots. */
        i: 0,
        timer: null,
        paused: false,
        settleTimer: null,
        gliding: false,

        init() {
            // Anyone who has asked for less motion gets a plain scrolling
            // list. Not a faster rotation - none. They can still reach every
            // page, because it is all still there to be scrolled.
            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                return;
            }

            if (this.pages().length < 2) {
                return;
            }

            this.timer = setInterval(() => this.paused || this.advance(), dwell + duration);
        },

        destroy() {
            clearInterval(this.timer);
            clearTimeout(this.settleTimer);
        },

        /** @returns {HTMLElement[]} */
        pages() {
            return this.$refs.track ? Array.from(this.$refs.track.children) : [];
        },

        /**
         * On to the next page, wrapping back to the first at the end.
         */
        advance() {
            this.show((this.i + 1) % this.pages().length);
        },

        /**
         * Move to page `index`, animating unless motion is unwanted.
         */
        show(index) {
            const page = this.pages()[index];

            if (! page) {
                return;
            }

            this.i = index;

            if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                this.$refs.track.scrollTop = page.offsetTop;

                return;
            }

            this.glideTo(page.offsetTop);
        },

        /**
         * Ease the scroll position to `target` over exactly `duration` ms.
         */
        glideTo(target) {
            const track = this.$refs.track;
            const from = track.scrollTop;
            const distance = target - from;
            const startedAt = performance.now();

            // So the scroll handler below knows this movement is ours and does
            // not read it as the visitor taking over.
            this.gliding = true;

            const step = (now) => {
                const elapsed = Math.min((now - startedAt) / duration, 1);

                // Cubic ease-out: quick to leave, slow to arrive, which is what
                // reads as slow motion rather than as a page loading badly.
                track.scrollTop = from + distance * (1 - Math.pow(1 - elapsed, 3));

                if (elapsed < 1) {
                    requestAnimationFrame(step);

                    return;
                }

                this.gliding = false;
            };

            requestAnimationFrame(step);
        },

        /**
         * The visitor scrolled. Follow them: light the dot for whichever page
         * they have landed nearest, and hold the timer while they read.
         */
        onScroll() {
            if (this.gliding) {
                return;
            }

            const top = this.$refs.track.scrollTop;
            const pages = this.pages();

            let nearest = 0;

            pages.forEach((page, index) => {
                if (Math.abs(page.offsetTop - top) < Math.abs(pages[nearest].offsetTop - top)) {
                    nearest = index;
                }
            });

            this.i = nearest;
            this.hold();
            this.release();
        },

        /**
         * Hold while somebody is reading, and for a while after they stop.
         */
        hold() {
            this.paused = true;
            clearTimeout(this.settleTimer);
        },

        release() {
            clearTimeout(this.settleTimer);
            this.settleTimer = setTimeout(() => (this.paused = false), dwell);
        },
    };
}
