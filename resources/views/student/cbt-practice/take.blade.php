{{--
    The practice paper, laid out the way a real CBT hall screen is: one question
    at a time, its passage above it, the options as lettered buttons, a timer, and
    a numbered palette that shows at a glance what is answered and what is not.

    WHAT THIS PAGE IS NOT GIVEN: which option is correct. The questions are sent
    to the browser with their label and text only, so nothing on this page (or in
    its network traffic) can be read to find the answer before submitting.
--}}
<x-student-layout :page-title="$attempt->exam->title()" page-subtitle="Answer each question, then submit">
    <div
        x-data="cbtAttempt({
            questions: @js($questions->values()->map(fn ($q, $i) => [
                'id' => $q->id,
                'number' => $q->question_number ?? $i + 1,
                'text' => \App\Support\CandidateText::clean($q->question_text),
                'passage' => \App\Support\CandidateText::clean($q->passage),
                'image' => $q->imageUrl(),
                'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'text' => \App\Support\CandidateText::clean($o->option_text)])->values(),
            ])),
            answers: @js($answeredMap),
            expiresAt: @js($attempt->expires_at?->toIso8601String()),
            saveUrl: @js(route('student.cbt-practice.attempts.answer', [$school, $attempt])),
            submitUrl: @js(route('student.cbt-practice.attempts.submit', [$school, $attempt])),
        })"
        x-init="init()"
        @keydown.window="onKey($event)"
        class="space-y-4"
    >
        {{-- Sticky header: question counter, timer, progress bar --}}
        <div class="sticky top-16 z-10 rounded-[10px] border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-800/95">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-bold text-gray-900 dark:text-white" x-text="`Question ${current + 1} of ${questions.length}`"></span>
                <span
                    class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-bold tabular-nums"
                    :class="secondsLeft !== null && secondsLeft < 60 ? 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'"
                >
                    <i class="fa-regular fa-clock"></i>
                    <span x-text="timeDisplay"></span>
                </span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-2 rounded-full bg-primary-600 transition-all duration-300 ease-out" :style="`width: ${progressPct}%`"></div>
            </div>
            <small class="field-hint mt-1.5"><span x-text="answeredCount"></span> of <span x-text="questions.length"></span> answered</small>
        </div>

        {{-- Current question --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <template x-for="(question, index) in questions" :key="question.id">
                <div x-show="current === index">
                    {{-- The comprehension or cloze passage the question belongs
                         to, exactly as the paper printed it. --}}
                    <template x-if="question.passage">
                        <div class="mb-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                <i class="fa-solid fa-align-left mr-1.5"></i>Read the passage
                            </p>
                            <div
                                class="mt-2 max-h-72 overflow-y-auto whitespace-pre-line rounded-[8px] border border-gray-200 bg-gray-50 p-4 text-sm leading-relaxed text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-300"
                                x-text="question.passage"
                            ></div>
                        </div>
                    </template>

                    <div class="flex gap-2">
                        <span class="shrink-0 text-sm font-bold text-primary-600 dark:text-primary-400" x-text="question.number + '.'"></span>
                        <p class="whitespace-pre-line text-sm font-bold text-gray-900 dark:text-white" x-text="question.text"></p>
                    </div>

                    <template x-if="question.image">
                        <img :src="question.image" class="mt-3 max-h-64 rounded-[8px] border border-gray-200 dark:border-gray-700" alt="">
                    </template>

                    <div class="mt-4 space-y-2">
                        <template x-for="option in question.options" :key="option.id">
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-[8px] border p-3 text-sm text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/50"
                                :class="answers[question.id] === option.id ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-700'"
                            >
                                <input
                                    type="radio"
                                    :name="'q' + question.id"
                                    :value="option.id"
                                    :checked="answers[question.id] === option.id"
                                    @change="select(question.id, option.id)"
                                    class="sr-only"
                                >
                                <span
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-bold transition-colors duration-150"
                                    :class="answers[question.id] === option.id
                                        ? 'border-primary-600 bg-primary-600 text-white'
                                        : 'border-gray-300 text-gray-600 dark:border-gray-600 dark:text-gray-300'"
                                    x-text="option.label"
                                ></span>
                                <span class="whitespace-pre-line" x-text="option.text"></span>
                            </label>
                        </template>
                    </div>

                    <button
                        type="button"
                        x-show="answers[question.id]"
                        x-cloak
                        @click="select(question.id, null)"
                        class="mt-3 text-xs font-semibold text-gray-500 transition-colors duration-150 hover:text-red-600 dark:text-gray-400"
                    >
                        <i class="fa-solid fa-eraser mr-1"></i>Clear my answer
                    </button>
                </div>
            </template>
        </div>

        {{-- Navigation --}}
        <div class="flex items-center justify-between gap-3">
            <button
                type="button"
                @click="prev()"
                :disabled="current === 0"
                class="btn rounded-[8px] border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-chevron-left mr-1.5 text-xs"></i>Previous
            </button>

            <span class="hidden text-xs text-gray-400 sm:block dark:text-gray-500">
                Tip: use <kbd class="rounded border border-gray-300 px-1 dark:border-gray-600">&larr;</kbd> <kbd class="rounded border border-gray-300 px-1 dark:border-gray-600">&rarr;</kbd> to move, <kbd class="rounded border border-gray-300 px-1 dark:border-gray-600">A</kbd>&ndash;<kbd class="rounded border border-gray-300 px-1 dark:border-gray-600">E</kbd> to answer
            </span>

            <div class="flex gap-3">
                <button
                    type="button"
                    x-show="current < questions.length - 1"
                    @click="next()"
                    class="btn rounded-[8px] bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md"
                >
                    Next<i class="fa-solid fa-chevron-right ml-1.5 text-xs"></i>
                </button>

                <button
                    type="button"
                    @click="confirmSubmit()"
                    class="btn rounded-[8px] bg-green-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-green-700 hover:shadow-md"
                >
                    <i class="fa-solid fa-paper-plane mr-1.5"></i>Submit Exam
                </button>
            </div>
        </div>

        {{-- Question palette --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-[4px] bg-primary-600"></span>Current</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-[4px] bg-primary-100 dark:bg-primary-900/40"></span>Answered</span>
                <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-[4px] bg-gray-100 dark:bg-gray-700"></span>Not answered</span>
            </div>
            <div class="grid grid-cols-8 gap-1.5 sm:grid-cols-10">
                <template x-for="(question, index) in questions" :key="question.id">
                    <button
                        type="button"
                        @click="current = index"
                        class="flex h-8 w-8 items-center justify-center rounded-[6px] text-xs font-bold transition-colors duration-150"
                        :class="current === index
                            ? 'bg-primary-600 text-white'
                            : (answers[question.id] ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400')"
                        x-text="question.number"
                    ></button>
                </template>
            </div>
        </div>

        <form :action="submitUrl" method="POST" x-ref="submitForm" class="hidden">
            @csrf
        </form>
    </div>

    <script>
        function cbtAttempt(config) {
            return {
                ...config,
                current: 0,
                secondsLeft: null,
                timeDisplay: '--:--',
                timerHandle: null,
                get answeredCount() {
                    return Object.values(this.answers).filter((value) => value).length;
                },
                get progressPct() {
                    if (this.questions.length === 0) return 0;
                    return Math.round((this.answeredCount / this.questions.length) * 100);
                },
                init() {
                    if (!this.expiresAt) return;
                    this.tick();
                    this.timerHandle = setInterval(() => this.tick(), 1000);
                },
                tick() {
                    this.secondsLeft = Math.max(0, Math.floor((new Date(this.expiresAt) - new Date()) / 1000));
                    const m = Math.floor(this.secondsLeft / 60);
                    const s = this.secondsLeft % 60;
                    this.timeDisplay = `${m}:${s.toString().padStart(2, '0')}`;
                    if (this.secondsLeft <= 0) {
                        clearInterval(this.timerHandle);
                        this.doSubmit();
                    }
                },
                select(questionId, optionId) {
                    this.answers[questionId] = optionId;
                    fetch(this.saveUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        },
                        body: JSON.stringify({ cbt_question_id: questionId, cbt_question_option_id: optionId }),
                    }).then((response) => {
                        if (response.status === 409) {
                            return response.json().then((data) => { window.location = data.redirect; });
                        }
                    });
                },
                // The hall keyboard: arrows to move between questions, a letter
                // to answer the one on screen.
                onKey(event) {
                    if (event.metaKey || event.ctrlKey || event.altKey) return;
                    if (['INPUT', 'TEXTAREA', 'SELECT'].includes(event.target.tagName)) return;

                    if (event.key === 'ArrowRight') return this.next();
                    if (event.key === 'ArrowLeft') return this.prev();

                    const question = this.questions[this.current];
                    if (!question) return;

                    const option = question.options.find((o) => o.label.toUpperCase() === event.key.toUpperCase());
                    if (option) {
                        event.preventDefault();
                        this.select(question.id, option.id);
                    }
                },
                next() {
                    if (this.current < this.questions.length - 1) this.current++;
                },
                prev() {
                    if (this.current > 0) this.current--;
                },
                confirmSubmit() {
                    const unanswered = this.questions.length - this.answeredCount;
                    const message = unanswered > 0
                        ? `${unanswered} question(s) are still unanswered. Submit anyway? You cannot change your answers after this.`
                        : 'Submit your answers? You cannot change them after this.';
                    if (!confirm(message)) return;
                    this.doSubmit();
                },
                doSubmit() {
                    clearInterval(this.timerHandle);
                    this.$refs.submitForm.submit();
                },
            };
        }
    </script>
</x-student-layout>
