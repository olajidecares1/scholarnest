/**
 * The evidence picker on the "Report a Concern" form.
 *
 * A report needs at least one photograph and takes at most eight, and the
 * reporter has to be able to see what they picked and drop any of it before
 * sending. None of that is possible with a plain file input: a FileList is
 * read-only, so "remove this one" cannot be expressed by editing the input.
 *
 * So the selection is held here and written back to the input through a
 * DataTransfer, which is the only way to hand a browser a new FileList. The
 * input stays the thing that actually submits - no JSON, no base64, no second
 * upload endpoint - it is simply kept in step with this list.
 *
 * EVERYTHING HERE IS A CONVENIENCE. PublicMisconductReportController validates
 * count, size and type again on arrival, and a reporter with JavaScript off
 * still gets a working form and honest server-side errors.
 */
import { prepareImageFile } from './image-upload-prep';

export default function reportEvidence(min = 1, max = 8, maxBytes = 5 * 1024 * 1024) {
    return {
        min,
        max,
        maxBytes,

        /** @type {{id: string, file: File, name: string, preview: string|null}[]} */
        files: [],

        /** Messages shown under the picker, rebuilt on every change. */
        problems: [],

        get canSubmit() {
            return this.files.length >= this.min && this.problems.length === 0;
        },

        /**
         * Take what the file dialog handed us, keeping what is allowed.
         *
         * Rejections are reported per reason rather than as one vague line:
         * somebody who picked nine photographs and one huge one needs to know
         * which of the two stopped them.
         */
        async add(fileList) {
            const rejected = { big: 0, type: 0, over: 0 };

            for (const original of Array.from(fileList)) {
                if (this.files.length >= this.max) {
                    rejected.over++;
                    continue;
                }

                // A photograph straight off a phone is often over 5MB. It is
                // shrunk and made upright first, so it is judged by what will
                // actually be sent. A video passes through untouched.
                const file = await prepareImageFile(original);

                if (file.size > this.maxBytes) {
                    rejected.big++;
                    continue;
                }

                if (! this.accepts(file)) {
                    rejected.type++;
                    continue;
                }

                this.files.push({
                    // Not the index: an id has to survive removal, or Alpine
                    // reuses the wrong preview when the list shifts up.
                    id: `${file.name}-${file.size}-${file.lastModified}-${Math.random()}`,
                    file,
                    name: file.name,
                    preview: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
                });
            }

            this.problems = [];

            if (rejected.big > 0) {
                this.problems.push(`${rejected.big} file(s) were larger than 5MB and were not added.`);
            }

            if (rejected.type > 0) {
                this.problems.push(`${rejected.type} file(s) were not a photograph or video and were not added.`);
            }

            if (rejected.over > 0) {
                this.problems.push(`Only ${this.max} files can be sent, so ${rejected.over} were not added.`);
            }

            this.sync();
        },

        remove(index) {
            const [gone] = this.files.splice(index, 1);

            // Released explicitly. Object URLs live until the document is
            // discarded, and a reporter trying several photographs would
            // otherwise leave every rejected one held in memory.
            if (gone?.preview) {
                URL.revokeObjectURL(gone.preview);
            }

            this.problems = [];
            this.sync();
        },

        accepts(file) {
            return [
                'image/jpeg', 'image/png', 'image/webp',
                'video/mp4', 'video/quicktime', 'video/webm',
            ].includes(file.type);
        },

        /**
         * Write the kept files back into the real input, so the form submits
         * exactly what the previews show.
         */
        sync() {
            const transfer = new DataTransfer();

            for (const item of this.files) {
                transfer.items.add(item.file);
            }

            this.$refs.input.files = transfer.files;
        },
    };
}
