// The Class Teacher's comment box on each pupil's row of the teacher portal's
// Report Cards list. Saves itself shortly after the teacher stops typing (and
// at once when they leave the box) to the same Class Teacher's Remark the
// report card prints, so there is no Save button to forget.
export default function teacherRemarkBox({ url, initial = '', published = false }) {
    return {
        url,
        text: initial ?? '',
        saved: initial ?? '',
        published,
        state: 'idle', // idle | saving | saved | error
        needsRepush: false,
        timer: null,
        sequence: 0,

        get dirty() {
            return this.text.trim() !== this.saved.trim();
        },

        queue() {
            clearTimeout(this.timer);
            this.state = this.dirty ? 'idle' : this.state;
            this.timer = setTimeout(() => this.save(), 900);
        },

        async save() {
            clearTimeout(this.timer);

            if (!this.dirty) {
                return;
            }

            const sent = this.text;
            const mine = ++this.sequence;
            this.state = 'saving';

            try {
                const response = await fetch(this.url, {
                    method: 'PUT',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ teacher_remark: sent }),
                });

                if (!response.ok) {
                    throw new Error('save failed');
                }

                const body = await response.json();

                // A later save has started since: let that one report.
                if (mine !== this.sequence) {
                    return;
                }

                this.saved = sent;
                this.needsRepush = Boolean(body.needs_repush);
                this.state = this.dirty ? 'idle' : 'saved';

                if (this.dirty) {
                    this.queue();
                }
            } catch (e) {
                if (mine === this.sequence) {
                    this.state = 'error';
                }
            }
        },

        // The same remark edited in the pupil's View panel: keep this box in step.
        syncFrom(event) {
            if (event.detail?.url !== this.url) {
                return;
            }

            this.text = event.detail.remark ?? '';
            this.saved = this.text;
            this.state = 'saved';
        },
    };
}
