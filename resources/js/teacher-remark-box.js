import Alpine from 'alpinejs';

// The Class Teacher's comment box on each pupil's row of the teacher portal's
// Report Cards list. Saves itself shortly after the teacher stops typing (and
// at once when they leave the box) to the same Class Teacher's Remark the
// report card prints, so nothing is lost if the Save button is never pressed.
//
// Every comment the teacher settles on is also remembered in their own list
// of saved comments (App\Models\TeacherRemark), and the Save button keeps one
// there for good. Under each box, "Comments" opens that list together with
// thirty ready-made suggestions (App\Support\SuggestedTeacherRemarks): tap
// one and it is this pupil's comment, saved. The list is one Alpine store for
// the whole page, so a comment saved under one pupil is offered at once under
// every other pupil's box.
const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

export function registerSavedRemarksStore(Alpine) {
    Alpine.store('savedRemarks', {
        items: [],
        suggested: [],
        forgetUrl: '',
        keepUrl: '',

        init() {
            const data = document.getElementById('saved-remarks-data');

            if (data) {
                const parsed = JSON.parse(data.textContent || '{}');
                this.items = parsed.items ?? [];
                this.suggested = parsed.suggested ?? [];
                this.forgetUrl = parsed.forgetUrl ?? '';
                this.keepUrl = parsed.keepUrl ?? '';
            }
        },

        // How many comments the list offers: the teacher's own, plus the
        // ready-made ones they have not already made their own.
        get total() {
            const own = new Set(this.items.map((item) => item.body.toLowerCase()));

            return this.items.length + this.suggested.filter((item) => !own.has(item.body.toLowerCase())).length;
        },

        // Saves a comment to the teacher's list for good. Resolves true once
        // the server has it.
        async keep(body) {
            const response = await fetch(this.keepUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
                body: JSON.stringify({ body }),
            });

            if (!response.ok) {
                return false;
            }

            this.replace((await response.json()).saved_remarks);

            return true;
        },

        replace(items) {
            if (Array.isArray(items)) {
                this.items = items;
            }
        },

        async forget(remark) {
            const before = this.items;
            this.items = this.items.filter((item) => item.uuid !== remark.uuid);

            try {
                const response = await fetch(this.forgetUrl.replace('__REMARK__', remark.uuid), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error('forget failed');
                }

                this.replace((await response.json()).saved_remarks);
            } catch (e) {
                this.items = before;
            }
        },
    });
}

export default function teacherRemarkBox({ url, initial = '', published = false }) {
    return {
        url,
        pickerOpen: false,
        search: '',
        draft: '', // the "Add a new comment" box inside the list
        keepState: 'idle', // idle | saving | kept | error, for the Save buttons
        text: initial ?? '',
        saved: initial ?? '',
        published,
        state: 'idle', // idle | saving | saved | error
        needsRepush: false,
        timer: null,
        sequence: 0,

        matches(item) {
            const term = this.search.trim().toLowerCase();
            const body = item.body.toLowerCase();

            return body !== this.text.trim().toLowerCase() && (term === '' || body.includes(term));
        },

        // The teacher's own comments matching the search, leaving out the one
        // already in this box.
        get choices() {
            return Alpine.store('savedRemarks').items.filter((item) => this.matches(item));
        },

        // The ready-made ones, less any the teacher already has as their own.
        get suggestions() {
            const own = new Set(Alpine.store('savedRemarks').items.map((item) => item.body.toLowerCase()));

            return Alpine.store('savedRemarks').suggested.filter((item) => !own.has(item.body.toLowerCase()) && this.matches(item));
        },

        // The Save button under the box: this pupil's comment saved now, and
        // kept on the teacher's list for next time.
        async keepCurrent() {
            if (this.text.trim() === '') {
                return;
            }

            await this.save();
            await this.keepText(this.text);
        },

        // The Save button inside the list: a new comment straight onto the
        // list, without putting it on this pupil's card.
        async keepDraft() {
            if (this.draft.trim() === '') {
                return;
            }

            if (await this.keepText(this.draft)) {
                this.draft = '';
            }
        },

        async keepText(body) {
            this.keepState = 'saving';

            try {
                const ok = await Alpine.store('savedRemarks').keep(body);
                this.keepState = ok ? 'kept' : 'error';
            } catch (e) {
                this.keepState = 'error';
            }

            if (this.keepState === 'kept') {
                setTimeout(() => {
                    if (this.keepState === 'kept') {
                        this.keepState = 'idle';
                    }
                }, 2500);
            }

            return this.keepState === 'kept';
        },

        togglePicker() {
            this.pickerOpen = !this.pickerOpen;
            this.search = '';
        },

        pick(remark) {
            this.text = remark.body;
            this.pickerOpen = false;
            this.search = '';
            this.save();
        },

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
                    headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
                    body: JSON.stringify({ teacher_remark: sent }),
                });

                if (!response.ok) {
                    throw new Error('save failed');
                }

                const body = await response.json();

                Alpine.store('savedRemarks').replace(body.saved_remarks);

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
