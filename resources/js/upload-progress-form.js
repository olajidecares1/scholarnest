/**
 * A file upload that reports what it is actually doing.
 *
 * The problem this solves: a CBT document upload has two phases that feel like
 * one long freeze. Sending the bytes is measurable. Extracting questions from
 * the document is not - the extractor gives no interim signal, and a large
 * scanned PDF can take a minute. A plain form post shows neither, so the page
 * simply hangs and the upload looks broken.
 *
 * The rule this file follows: never show a percentage we have not measured.
 * The bar is determinate while bytes are in flight, because the browser tells
 * us exactly how many have gone. The moment the last byte lands the percentage
 * is dropped entirely and the bar becomes indeterminate, because from there we
 * genuinely do not know how long extraction will take. Running a fake number
 * up to 100% while the server was still working would make a long wait look
 * like a failed one.
 */
export default function uploadProgressForm({ maxMb = 21 } = {}) {
    return {
        /** idle | uploading | processing | done | failed */
        phase: 'idle',

        /** Percent complete, or null when no honest number exists. */
        percent: null,

        heading: '',
        message: '',
        sentBytes: 0,
        totalBytes: 0,
        redirectUrl: null,

        /** Handle for the status poll, so it can be stopped. */
        pollTimer: null,

        /** Consecutive failed polls, tolerated before giving up. */
        pollFailures: 0,

        get icon() {
            return {
                uploading: 'fa-arrow-up-from-bracket text-primary-500',
                processing: 'fa-gears text-primary-500',
                done: 'fa-circle-check text-green-600',
                failed: 'fa-circle-exclamation text-red-600',
            }[this.phase] ?? 'fa-arrow-up-from-bracket';
        },

        send(form) {
            const file = form.querySelector('input[type="file"]')?.files?.[0];

            if (!file) {
                return;
            }

            // Catch an oversized file here rather than spending a minute
            // uploading something the server is bound to reject.
            if (file.size > maxMb * 1024 * 1024) {
                this.fail(`That file is ${this.formatBytes(file.size)}. The limit is ${maxMb}MB.`);

                return;
            }

            this.phase = 'uploading';
            this.percent = 0;
            this.sentBytes = 0;
            this.totalBytes = file.size;
            this.heading = `Uploading ${file.name}`;
            this.message = 'Sending the document to the server.';

            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action, true);
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            // Real numbers, straight from the browser.
            xhr.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable) {
                    return;
                }

                this.sentBytes = event.loaded;
                this.totalBytes = event.total;
                this.percent = Math.round((event.loaded / event.total) * 100);
            });

            // The bytes have all been sent, but the server has not answered
            // yet - it is storing the file and queueing the work. This is the
            // exact moment a naive implementation would sit at 100%, so the
            // percentage is dropped here instead.
            xhr.upload.addEventListener('load', () => {
                this.enterProcessing('Upload complete. Waiting for the server…');
            });

            xhr.addEventListener('load', () => {
                if (xhr.status === 201 || xhr.status === 200) {
                    this.onUploaded(xhr);

                    return;
                }

                if (xhr.status === 422) {
                    this.fail(this.validationMessage(xhr));

                    return;
                }

                if (xhr.status === 413) {
                    this.fail(`The server rejected the file for being too large. The limit is ${maxMb}MB.`);

                    return;
                }

                this.fail(`The upload failed (error ${xhr.status}). Please try again.`);
            });

            xhr.addEventListener('error', () => this.fail('The upload failed. Check your connection and try again.'));
            xhr.addEventListener('abort', () => this.fail('The upload was cancelled.'));
            xhr.addEventListener('timeout', () => this.fail('The upload timed out. Please try again.'));

            xhr.send(new FormData(form));
        },

        onUploaded(xhr) {
            let payload = {};

            try {
                payload = JSON.parse(xhr.responseText);
            } catch {
                // A success status with a body we cannot read still means the
                // document is stored; fall back to a plain reload.
                window.location.reload();

                return;
            }

            this.redirectUrl = payload.redirect_url ?? null;

            if (!payload.status_url) {
                this.finish();

                return;
            }

            this.enterProcessing('Reading the document and extracting questions…');
            this.poll(payload.status_url);
        },

        /**
         * Leave the measurable phase. Dropping `percent` to null is what makes
         * the bar go indeterminate.
         */
        enterProcessing(message) {
            if (this.phase === 'done' || this.phase === 'failed') {
                return;
            }

            this.phase = 'processing';
            this.percent = null;
            this.heading = 'Processing';
            this.message = message;
        },

        poll(statusUrl) {
            this.pollTimer = window.setInterval(async () => {
                try {
                    const response = await fetch(statusUrl, {
                        headers: { Accept: 'application/json' },
                    });

                    if (!response.ok) {
                        throw new Error(String(response.status));
                    }

                    const state = await response.json();
                    this.pollFailures = 0;
                    this.message = state.message;

                    // The queue is not running. Say so plainly instead of
                    // spinning forever - this is recoverable, and the person
                    // watching can do something about it.
                    if (state.stalled) {
                        this.heading = 'Waiting to start';
                    } else if (state.label) {
                        this.heading = state.label;
                    }

                    if (state.in_progress) {
                        return;
                    }

                    this.stopPolling();

                    if (state.status === 'failed') {
                        this.fail(state.error || 'Extraction failed.');

                        return;
                    }

                    this.finish(state);
                } catch {
                    // A single dropped poll is not a failure; several in a row
                    // means we have lost the server.
                    this.pollFailures += 1;

                    if (this.pollFailures >= 5) {
                        this.stopPolling();
                        this.message = 'Lost contact with the server. The document is still being processed — reload to check.';
                    }
                }
            }, 2000);
        },

        finish(state = {}) {
            this.stopPolling();
            this.phase = 'done';
            this.percent = 100;
            this.heading = 'Completed';
            this.message = state.question_count
                ? `${state.question_count} question(s) extracted.`
                : (state.message || 'The document was processed successfully.');

            // Land on the page that shows the result, now that there is one.
            if (this.redirectUrl) {
                window.setTimeout(() => {
                    window.location.href = this.redirectUrl;
                }, 900);
            }
        },

        fail(message) {
            this.stopPolling();
            this.phase = 'failed';
            this.percent = 100;
            this.heading = 'Failed';
            this.message = message;
        },

        /**
         * Pull the first validation message out of a 422 so the person is told
         * what was wrong with the file rather than "error 422".
         */
        validationMessage(xhr) {
            try {
                const body = JSON.parse(xhr.responseText);
                const first = Object.values(body.errors ?? {})[0];

                return (Array.isArray(first) ? first[0] : first) || body.message || 'The file was rejected.';
            } catch {
                return 'The file was rejected.';
            }
        },

        stopPolling() {
            if (this.pollTimer) {
                window.clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        reset() {
            this.stopPolling();
            this.phase = 'idle';
            this.percent = null;
            this.sentBytes = 0;
            this.totalBytes = 0;
            this.pollFailures = 0;
        },

        formatBytes(bytes) {
            if (bytes < 1024) {
                return `${bytes} B`;
            }

            if (bytes < 1024 * 1024) {
                return `${(bytes / 1024).toFixed(0)} KB`;
            }

            return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
        },
    };
}
