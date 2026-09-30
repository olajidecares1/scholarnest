/*
 * A profile photograph that cannot be loaded shows the person's initials.
 *
 * Photographs are served from private storage through a signed link. When the
 * file behind a link has gone (an upload lost from a server whose disk is
 * reset by each deploy, say) the browser would otherwise draw its own broken-
 * image mark, a "?" in a box on an iPhone. Any <img data-fallback="AB"> is
 * replaced, on error, by a box of the same size holding those letters.
 *
 * A photograph served from the protected /media/ route is covered even
 * without the attribute (a page cached or compiled before the attribute was
 * added, say): it falls back to initials taken from its alt text, or to a
 * person icon.
 *
 * Listened for at the document, in the capture phase, because image errors do
 * not bubble; that also covers images Alpine adds after the page loads.
 */
const isPhoto = (img) =>
    img.hasAttribute('data-fallback') || /\/media\/(student|staff|guardian|user)\//.test(img.getAttribute('src') || '');

const initialsFrom = (text) =>
    String(text || '')
        .trim()
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');

export function installPhotoFallback() {
    if (typeof document === 'undefined') {
        return;
    }

    const replace = (img) => {
            const box = document.createElement('span');
            box.className = `${img.className} photo-fallback`;
            box.setAttribute('role', 'img');
            box.setAttribute('aria-label', img.alt || 'No photo');

            const letters = (img.getAttribute('data-fallback') ?? initialsFrom(img.alt)).trim();

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

            if (img instanceof HTMLImageElement && isPhoto(img)) {
                replace(img);
            }
        },
        true,
    );

    // This script is a deferred module, so a photograph that failed while the
    // page was still loading has already fired its error. Catch those too.
    const sweep = () => document
        .querySelectorAll('img')
        .forEach((img) => {
            if (isPhoto(img) && img.complete && img.naturalWidth === 0 && img.getAttribute('src')) {
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
