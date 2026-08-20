<x-student-layout :page-title="$attempt->test->title" page-subtitle="Answer each question, then move to the next">
    <div
        x-data="cbtTestAttempt({
            questions: @js($questions->map(fn ($q) => [
                'id' => $q->id,
                'text' => $q->question_text,
                'image' => $q->imageUrl(),
                'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'text' => $o->option_text]),
            ])),
            answers: @js($answeredMap),
            expiresAt: @js($attempt->expires_at?->toIso8601String()),
            saveUrl: @js(route('student.tests.attempts.answer', [$school, $attempt])),
            submitUrl: @js(route('student.tests.attempts.submit', [$school, $attempt])),
        })"
        x-init="init()"
        class="space-y-4"
    >
        {{-- Sticky header: question counter, timer, progress bar --}}
        <div class="sticky top-16 z-10 rounded-[10px] border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-800/95">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-bold text-gray-900 dark:text-white" x-text="`Question ${current + 1} of ${questions.length}`"></span>
                <span
                    class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-bold"
                    :class="secondsLeft !== null && secondsLeft < 60 ? 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400' : 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'"
                >
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6" /><path d="M12 7v5l3.5 2" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" /></svg>
                    <span x-text="timeDisplay"></span>
                </span>
            </div>
            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-2 rounded-full bg-primary-600 transition-all duration-300 ease-out" :style="`width: ${progressPct}%`"></div>
            </div>
            <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400"><span x-text="Object.keys(answers).length"></span> of <span x-text="questions.length"></span> answered</p>
        </div>

        {{-- Current question --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <template x-for="(question, index) in questions" :key="question.id">
                <div x-show="current === index">
                    <p class="text-sm font-bold text-gray-900 dark:text-white" x-text="question.text"></p>
                    <img x-show="question.image" :src="question.image" class="mt-3 max-h-56 rounded-[8px]" alt="">

                    <div class="mt-4 space-y-2">
                        <template x-for="option in question.options" :key="option.id">
                            <label
                                class="flex cursor-pointer items-center gap-3 rounded-[8px] border p-3 text-sm text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/50"
                                :class="answers[question.id] === option.id ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20' : 'border-gray-200 dark:border-gray-700'"
                            >
                                <input
                                    type="radio"
                                    :name="'q' + question.id"
                                    :value="option.id"
                                    :checked="answers[question.id] === option.id"
                                    @change="select(question.id, option.id)"
                                    class="h-4 w-4 shrink-0 border-gray-300 text-primary-600 focus:ring-primary-500"
                                >
                                <span><span class="font-semibold" x-text="option.label + '.'"></span> <span x-text="option.text"></span></span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Question palette --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="grid grid-cols-8 gap-1.5 sm:grid-cols-10">
                <template x-for="(question, index) in questions" :key="question.id">
                    <button
                        type="button"
                        @click="current = index"
                        class="flex h-8 w-8 items-center justify-center rounded-[6px] text-xs font-bold transition-colors duration-150"
                        :class="current === index
                            ? 'bg-primary-600 text-white'
                            : (answers[question.id] ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400')"
                        x-text="index + 1"
                    ></button>
                </template>
            </div>
        </div>

        {{-- Navigation --}}
        <div class="flex items-center justify-between gap-3">
            <button
                type="button"
                @click="prev()"
                :disabled="current === 0"
                class="rounded-[8px] border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                Previous
            </button>

            <button
                type="button"
                x-show="current < questions.length - 1"
                @click="next()"
                class="rounded-[8px] bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md"
            >
                Next
            </button>

            <button
                type="button"
                x-show="current === questions.length - 1"
                @click="confirmSubmit()"
                class="rounded-[8px] bg-green-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-green-700 hover:shadow-md"
            >
                Submit Test
            </button>
        </div>

        <form :action="submitUrl" method="POST" x-ref="submitForm" class="hidden">
            @csrf
        </form>
    </div>

    <script>
        function cbtTestAttempt(config) {
            return {
                ...config,
                current: 0,
                secondsLeft: null,
                timeDisplay: '--:--',
                timerHandle: null,
                get progressPct() {
                    if (this.questions.length === 0) return 0;
                    return Math.round((Object.keys(this.answers).length / this.questions.length) * 100);
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
                        body: JSON.stringify({ cbt_test_question_id: questionId, cbt_test_question_option_id: optionId }),
                    }).then((response) => {
                        if (response.status === 409) {
                            return response.json().then((data) => { window.location = data.redirect; });
                        }
                    });
                },
                next() {
                    if (this.current < this.questions.length - 1) this.current++;
                },
                prev() {
                    if (this.current > 0) this.current--;
                },
                confirmSubmit() {
                    const unanswered = this.questions.length - Object.keys(this.answers).length;
                    if (unanswered > 0 && !confirm(`${unanswered} question(s) unanswered. Submit anyway?`)) return;
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
