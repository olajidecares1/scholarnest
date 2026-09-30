/*
 * A profile photograph that cannot be loaded shows the person's initials.
 *
 * Photographs are served from private storage through a signed link. When the
 * file behind a link has gone (an upload lost from a server whose disk is
 * reset by each deploy, say) the browser would otherwise draw its own broken-
 * image mark, a "?" in a box on an iPhone. Any <img data-fallback="AB"> is
 * replaced, on error, by a box of the same size holding those letters.
 *
 * Listened for at the document, in the capture phase, because image errors do
 * not bubble; that also covers images Alpine adds after the page loads.
 */
export function installPhotoFallback() {
    if (typeof document === 'undefined') {
        return;
    }

    const replace = (img) => {
            const box = document.createElement('span');
            box.className = `${img.className} photo-fallback`;
            box.setAttribute('role', 'img');
            box.setAttribute('aria-label', img.alt || 'No photo');

            const letters = (img.getAttribute('data-fallback') || '').trim();

            if (letters) {
                box.textContent = letters;
            } else {
                box.innerHTML = '<i class="fa-solid fa-user" aria-hidden="true"></i>';
            }

            img.replaceWith(box);
    };

    document.addEventListener(
        'error',
        (event) => {
            const img = event.target;

            if (img instanceof HTMLImageElement && img.hasAttribute('data-fallback')) {
                replace(img);
            }
        },
        true,
    );

    // This script is a deferred module, so a photograph that failed while the
    // page was still loading has already fired its error. Catch those too.
    const sweep = () => document
        .querySelectorAll('img[data-fallback]')
        .forEach((img) => {
            if (img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
                replace(img);
            }
        });

    if (document.readyState === 'complete') {
        sweep();
    } else {
        sweep();
        window.addEventListener('load', sweep, { once: true });
    }
}

export default installPhotoFallback;
