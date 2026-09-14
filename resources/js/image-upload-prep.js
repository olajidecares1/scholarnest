/**
 * Large photographs are made smaller IN THE BROWSER, before they are sent.
 *
 * A photograph straight off a modern phone is 4000-8000 pixels wide and
 * 3-12MB. Sending that over a school's mobile connection is slow, is the
 * upload most likely to be interrupted, and can exceed the server's request
 * limit before the application sees it at all. Nothing that big is ever
 * shown: the server scales every image down to the size it is used at.
 *
 * So an image wider than it will ever be displayed is drawn onto a canvas at
 * that size and sent instead. createImageBitmap applies the photograph's EXIF
 * orientation as it decodes, so a portrait photo is upright in the file that
 * is sent. On Safari, the only browser that can decode HEIC, an iPhone HEIC
 * photo comes out of this as a JPEG the server can read.
 *
 * THIS IS ONLY EVER A CONVENIENCE. Anything it cannot do, an old browser, a
 * format it cannot decode, a canvas that fails, leaves the original file
 * untouched, and the server validates and processes whatever arrives exactly
 * as it would have anyway (App\Services\Uploads\ImageProcessor).
 *
 * Only images are touched. A PDF, a Word document or a video passes through as
 * it is, so this is safe on mixed inputs like the receipt field.
 */

/** Files this has already dealt with, so nothing is shrunk twice. */
const prepared = new WeakSet();

const DECODABLE = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

/** The largest edge sent when a field does not say otherwise. */
export const DEFAULT_MAX_EDGE = 2560;

export function isHeic(file) {
    return /image\/hei[cf]/i.test(file?.type || '') || /\.(heic|heif)$/i.test(file?.name || '');
}

export function markPrepared(file) {
    if (file) {
        prepared.add(file);
    }

    return file;
}

/**
 * The file to send in place of `file`: a smaller, upright copy when that
 * helps, otherwise the very same File.
 *
 * @param {File} file
 * @param {{ maxEdge?: number }} options
 * @returns {Promise<File>}
 */
export async function prepareImageFile(file, { maxEdge = DEFAULT_MAX_EDGE } = {}) {
    if (!file || prepared.has(file)) {
        return file;
    }

    const type = (file.type || '').toLowerCase();
    const heic = isHeic(file);

    if (!DECODABLE.includes(type) && !heic) {
        return file;
    }

    if (typeof createImageBitmap !== 'function') {
        return markPrepared(file);
    }

    let bitmap;

    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        // Not decodable here (HEIC outside Safari, a damaged file). The server
        // decides, and says why if it cannot accept it.
        return markPrepared(file);
    }

    try {
        const longest = Math.max(bitmap.width, bitmap.height);
        const needsResize = longest > maxEdge;

        // Already small enough and in a format the server reads: sent exactly
        // as it is, so it is never recompressed for nothing.
        if (!needsResize && !heic) {
            return markPrepared(file);
        }

        const scale = needsResize ? maxEdge / longest : 1;
        const width = Math.max(1, Math.round(bitmap.width * scale));
        const height = Math.max(1, Math.round(bitmap.height * scale));

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const context = canvas.getContext('2d');
        context.imageSmoothingEnabled = true;
        context.imageSmoothingQuality = 'high';
        context.drawImage(bitmap, 0, 0, width, height);

        // PNG and WebP may be transparent, a logo, usually, so they keep
        // their format. Everything else, including HEIC, becomes JPEG.
        const outputType = type === 'image/png' || type === 'image/webp' ? type : 'image/jpeg';
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, outputType, 0.92));

        if (!blob) {
            return markPrepared(file);
        }

        const extension = { 'image/png': 'png', 'image/webp': 'webp' }[outputType] || 'jpg';
        const stem = (file.name || 'photo').replace(/\.[^.]+$/, '') || 'photo';

        return markPrepared(new File([blob], `${stem}.${extension}`, { type: outputType, lastModified: Date.now() }));
    } catch {
        return markPrepared(file);
    } finally {
        bitmap.close?.();
    }
}

/** Replace the files held by an <input type="file">. */
export function setInputFiles(input, files) {
    const transfer = new DataTransfer();

    files.filter(Boolean).forEach((file) => transfer.items.add(file));
    input.files = transfer.files;
}

function acceptsImages(input) {
    const accept = (input.getAttribute('accept') || '').toLowerCase();

    return accept === '' || /image\/|\.(jpe?g|png|webp|heic|heif)\b/.test(accept);
}

function toggleBusy(form, busy) {
    form.toggleAttribute('aria-busy', busy);

    form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach((button) => {
        if (busy) {
            button.dataset.imagePrepWasDisabled = button.disabled ? '1' : '0';
            button.disabled = true;
        } else if (button.dataset.imagePrepWasDisabled === '0') {
            button.disabled = false;
        }
    });
}

/**
 * Shrink large photographs in any ordinary form, just before it submits.
 *
 * Every image field in the application gets this without its view being
 * touched. The first submit is held back while the images are prepared, then
 * the form is submitted again, through requestSubmit(), so the button that
 * was pressed, the browser's own validation and every other submit handler
 * behave exactly as they would have. A field can opt out with
 * data-no-image-prep, and data-max-edge sets how large it may be sent.
 */
export function installImageUploadPrep(root = document) {
    root.addEventListener(
        'submit',
        async (event) => {
            const form = event.target;

            if (!(form instanceof HTMLFormElement) || form.dataset.imagePrepReady === '1') {
                return;
            }

            const inputs = Array.from(form.querySelectorAll('input[type="file"]')).filter(
                (input) =>
                    !input.hasAttribute('data-no-image-prep') &&
                    acceptsImages(input) &&
                    Array.from(input.files || []).some((file) => !prepared.has(file)),
            );

            if (inputs.length === 0 || typeof form.requestSubmit !== 'function') {
                return;
            }

            // Held back before any other handler sees it, so nothing reacts to
            // a submission that has not happened yet.
            event.preventDefault();
            event.stopImmediatePropagation();

            const submitter = event.submitter;
            toggleBusy(form, true);

            try {
                for (const input of inputs) {
                    const maxEdge = Number(input.dataset.maxEdge) || DEFAULT_MAX_EDGE;
                    const files = await Promise.all(
                        Array.from(input.files).map((file) => prepareImageFile(file, { maxEdge })),
                    );

                    setInputFiles(input, files);
                }
            } finally {
                toggleBusy(form, false);
            }

            form.dataset.imagePrepReady = '1';

            try {
                submitter && submitter.form === form ? form.requestSubmit(submitter) : form.requestSubmit();
            } finally {
                delete form.dataset.imagePrepReady;
            }
        },
        true,
    );
}
