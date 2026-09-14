/**
 * The full-screen gallery viewer.
 *
 * It is handed EVERY image in the gallery, not the three the panel happens to
 * be showing. That is the whole point: a visitor who opens the third photograph
 * can walk forward to the twelfth without the panel's rotation having anything
 * to do with it. The thumbnails pass their position in the full list, so the
 * grouping into threes and the viewer's idea of "next" stay independent.
 *
 * Note the method is openAt() rather than show(). The thumbnails sit inside the
 * marquee's Alpine scope, which has its own show() for the dots, and an inner
 * scope shadows an outer one, a thumbnail calling show() would have scrolled
 * the panel instead of opening anything.
 */
export default function galleryViewer(images = []) {
    return {
        images,
        isOpen: false,
        index: 0,

        /** The element that opened the viewer, so focus can go back to it. */
        opener: null,

        /** Where the page was, so closing puts the visitor back. */
        scrollY: 0,

        /**
         * @param {number} index position in the FULL gallery, not in the group
         */
        openAt(index, event) {
            if (! this.images[index]) {
                return;
            }

            this.index = index;
            this.opener = event?.currentTarget ?? null;
            this.lockPage();
            this.isOpen = true;

            this.$nextTick(() => this.$refs.closeButton?.focus());
        },

        close() {
            if (! this.isOpen) {
                return;
            }

            this.isOpen = false;
            this.unlockPage();

            // Back to the thumbnail they came from, not the top of the page.
            this.opener?.focus();
            this.opener = null;
        },

        next() {
            if (this.hasNext) {
                this.index += 1;
            }
        },

        previous() {
            if (this.hasPrevious) {
                this.index -= 1;
            }
        },

        // Stopping at the ends rather than wrapping, so the buttons can be
        // disabled honestly and nothing ever asks for an image that is not
        // there.
        get hasNext() {
            return this.index < this.images.length - 1;
        },

        get hasPrevious() {
            return this.index > 0;
        },

        get current() {
            return this.images[this.index] ?? null;
        },

        /**
         * Hold the page still behind the viewer.
         *
         * Hiding the body's scrollbar takes its width out of the layout and
         * shunts the whole page sideways, which is very visible on the way
         * back out, so the width it took is given back as padding.
         */
        lockPage() {
            this.scrollY = window.scrollY;

            const scrollbar = window.innerWidth - document.documentElement.clientWidth;

            document.body.style.overflow = 'hidden';

            if (scrollbar > 0) {
                document.body.style.paddingRight = `${scrollbar}px`;
            }
        },

        unlockPage() {
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            window.scrollTo(0, this.scrollY);
        },
    };
}
