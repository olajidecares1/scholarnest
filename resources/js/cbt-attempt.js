/**
 * The student's side of a CBT: one question at a time, a palette, a clock, and
 * answers that survive a bad connection.
 *
 * Two things here are less obvious than they look.
 *
 * The clock is anchored to the server, not the device. A tablet with a wrong
 * date would otherwise show a student minutes they do not have, or take away
 * minutes they do. The server decides when an attempt expires regardless, so
 * getting this wrong never let anyone cheat, it just meant the countdown on
 * screen disagreed with the one being enforced, which is its own cruelty during
 * an exam.
 *
 * Saving retries. The previous version fired the save and ignored the result,
 * so an answer chosen while the connection dropped was lost with no sign
 * anything had gone wrong, the option stayed highlighted, the student moved
 * on, and the mark never existed. Answers now queue, retry with backoff, and
 * the header says plainly whether the work is safe.
 */
export default function cbtAttempt(config) {
    return {
        ...config,

        current: 0,

        /** Question id -> option id, as the student sees it. */
        answers: config.answers ?? {},

        /** Question ids whose latest answer has not reached the server yet. */
        pending: new Set(),

        /** idle | saving | saved | retrying */
        saveState: 'idle',

        secondsLeft: null,
        timeDisplay: '--:--',
        timerHandle: null,

        /** Milliseconds to add to this device's clock to get server time. */
        clockOffset: 0,

        showInstructions: Boolean(config.instructions),

        init() {
            // Anchor to the server's clock, measured once at load.
            if (this.serverNow) {
                this.clockOffset = new Date(this.serverNow).getTime() - Date.now();
            }

            if (this.expiresAt) {
                this.tick();
                this.timerHandle = setInterval(() => this.tick(), 1000);
            }

            // A student who reloads mid-exam should not lose an answer that was
            // still in flight.
            window.addEventListener('beforeunload', (event) => {
                if (this.pending.size === 0) {
                    return;
                }

                event.preventDefault();
                event.returnValue = '';
            });
        },

        get answeredCount() {
            return Object.values(this.answers).filter((value) => value !== null && value !== undefined).length;
        },

        get unansweredCount() {
            return this.questions.length - this.answeredCount;
        },

        get progressPct() {
            if (this.questions.length === 0) {
                return 0;
            }

            return Math.round((this.answeredCount / this.questions.length) * 100);
        },

        get saveMessage() {
            return {
                saving: 'Saving…',
                saved: 'All answers saved',
                retrying: 'Connection lost. Retrying…',
            }[this.saveState] ?? '';
        },

        isAnswered(questionId) {
            const value = this.answers[questionId];

            return value !== null && value !== undefined;
        },

        tick() {
            const serverTime = Date.now() + this.clockOffset;
            this.secondsLeft = Math.max(0, Math.floor((new Date(this.expiresAt).getTime() - serverTime) / 1000));

            const hours = Math.floor(this.secondsLeft / 3600);
            const minutes = Math.floor((this.secondsLeft % 3600) / 60);
            const seconds = this.secondsLeft % 60;

            this.timeDisplay = hours > 0
                ? `${hours}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`
                : `${minutes}:${seconds.toString().padStart(2, '0')}`;

            if (this.secondsLeft <= 0) {
                clearInterval(this.timerHandle);
                this.doSubmit();
            }
        },

        select(questionId, optionId) {
            this.answers[questionId] = optionId;
            this.pending.add(questionId);
            this.save(questionId, optionId, 0);
        },

        /**
         * Persist one answer, retrying until it lands.
         *
         * Backs off so a dropped connection does not turn into a request storm,
         * but keeps trying indefinitely: the student is sitting an exam and has
         * nothing else to do with the answer but get it saved.
         */
        save(questionId, optionId, attempt) {
            this.saveState = attempt === 0 ? 'saving' : 'retrying';

            fetch(this.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({
                    cbt_test_question_id: questionId,
                    cbt_test_question_option_id: optionId,
                }),
            })
                .then((response) => {
                    // The server has ended the attempt, time ran out while the
                    // page was open. Go where it says rather than keep trying.
                    if (response.status === 409) {
                        return response.json().then((data) => {
                            window.location = data.redirect;
                        });
                    }

                    if (! response.ok) {
                        throw new Error(String(response.status));
                    }

                    // Only clear the flag if this is still the answer on screen;
                    // a later choice for the same question owns it now.
                    if (this.answers[questionId] === optionId) {
                        this.pending.delete(questionId);
                    }

                    this.saveState = this.pending.size === 0 ? 'saved' : 'saving';

                    return null;
                })
                .catch(() => {
                    if (this.answers[questionId] !== optionId) {
                        // Superseded; the newer save is the one that matters.
                        return;
                    }

                    const delay = Math.min(1000 * 2 ** attempt, 15000);
                    this.saveState = 'retrying';
                    setTimeout(() => this.save(questionId, optionId, attempt + 1), delay);
                });
        },

        go(index) {
            this.current = Math.max(0, Math.min(index, this.questions.length - 1));
        },

        next() {
            this.go(this.current + 1);
        },

        prev() {
            this.go(this.current - 1);
        },

        confirmSubmit() {
            if (this.pending.size > 0 && ! window.confirm('Some answers have not saved yet. Submit anyway?')) {
                return;
            }

            if (this.unansweredCount > 0
                && ! window.confirm(`${this.unansweredCount} question(s) are unanswered. Submit anyway?`)) {
                return;
            }

            this.doSubmit();
        },

        doSubmit() {
            clearInterval(this.timerHandle);

            // Past this point the page is being replaced by the result, so the
            // unsaved-answer prompt would only get in the way.
            this.pending.clear();
            this.$refs.submitForm.submit();
        },
    };
}
