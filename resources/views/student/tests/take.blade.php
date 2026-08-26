{{-- The examination hall.

     Laid out the way a candidate expects a CBT to look: one question at a
     time, a numbered palette showing what is done, a clock, and a submit
     button that tells you what you are about to leave unanswered.

     The palette is deliberately always visible on wide screens and collapsible
     on narrow ones - on a phone it would otherwise push the question itself
     below the fold, and the question is what the student is here for. --}}
<x-student-layout :page-title="$attempt->test->title" :page-subtitle="$attempt->test->subject.' · '.$attempt->test->class_name">
    <div
        x-data="cbtAttempt({
            questions: @js($questions->map(fn ($q, $i) => [
                'id' => $q->id,
                'number' => $i + 1,
                'text' => $q->question_text,
                'marks' => $q->marks,
                'image' => $q->imageUrl(),
                'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'label' => $o->label, 'text' => $o->option_text]),
            ])),
            answers: @js((object) $answeredMap),
            instructions: @js($attempt->test->instructions),
            expiresAt: @js($attempt->expires_at?->toIso8601String()),
            serverNow: @js(now()->toIso8601String()),
            saveUrl: @js(route('student.tests.attempts.answer', [$school, $attempt])),
            submitUrl: @js(route('student.tests.attempts.submit', [$school, $attempt])),
        })"
        x-init="init()"
        class="space-y-4"
    >
        {{-- Rubric, shown once at the start. Dismissible, because a student who
             has read it should not have to scroll past it for the whole paper. --}}
        <div
            x-show="showInstructions"
            x-cloak
            class="rounded-[10px] border border-primary-200 bg-primary-50 p-5 dark:border-primary-800 dark:bg-primary-900/20"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-sm font-bold text-primary-900 dark:text-primary-200">
                        <i class="fa-solid fa-circle-info"></i>
                        Instructions
                    </p>
                    <p class="mt-2 whitespace-pre-line text-[13px] leading-[1.7] text-primary-900/90 dark:text-primary-200/90" x-text="instructions"></p>
                </div>
                <button
                    type="button"
                    x-on:click="showInstructions = false"
                    class="shrink-0 rounded-[6px] px-2 py-1 text-xs font-semibold text-primary-700 transition hover:bg-primary-100 dark:text-primary-300 dark:hover:bg-primary-900/40"
                >
                    Got it
                </button>
            </div>
        </div>

        {{-- Counter, clock, save state, progress. --}}
        <div class="sticky top-16 z-10 rounded-[10px] border border-gray-200 bg-white/95 p-4 shadow-sm backdrop-blur dark:border-gray-700 dark:bg-gray-800/95">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <span class="text-sm font-bold text-gray-900 dark:text-white" x-text="`Question ${current + 1} of ${questions.length}`"></span>

                <span
                    class="flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-bold tabular-nums"
                    :class="secondsLeft !== null && secondsLeft < 60
                        ? 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400'
                        : 'bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-400'"
                >
                    <i class="fa-regular fa-clock text-[13px]"></i>
                    <span x-text="timeDisplay"></span>
                </span>
            </div>

            <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-700">
                <div class="h-2 rounded-full bg-primary-600 transition-all duration-300 ease-out" :style="`width: ${progressPct}%`"></div>
            </div>

            <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                <p class="field-hint">
                    <span x-text="answeredCount"></span> answered ·
                    <span x-text="unansweredCount"></span> remaining
                </p>

                {{-- Whether the student's work is actually safe. Silence here
                     was how a lost answer used to go unnoticed. --}}
                <p
                    x-show="saveMessage"
                    x-cloak
                    class="flex items-center gap-1.5 text-[11px] font-semibold"
                    :class="saveState === 'retrying' ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400 dark:text-gray-500'"
                >
                    <i
                        class="text-[10px]"
                        :class="saveState === 'saved' ? 'fa-solid fa-circle-check' : 'fa-solid fa-arrows-rotate fa-spin'"
                    ></i>
                    <span x-text="saveMessage"></span>
                </p>
            </div>
        </div>

        {{-- The question. --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <template x-for="(question, index) in questions" :key="question.id">
                <div x-show="current === index">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-bold leading-[1.7] text-gray-900 dark:text-white">
                            <span class="text-primary-600 dark:text-primary-400" x-text="question.number + '.'"></span>
                            <span x-text="question.text"></span>
                        </p>
                        <span
                            x-show="question.marks > 1"
                            class="shrink-0 rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-bold text-gray-600 dark:bg-gray-700 dark:text-gray-300"
                            x-text="question.marks + ' marks'"
                        ></span>
                    </div>

                    <img x-show="question.image" :src="question.image" class="mt-3 max-h-56 rounded-[8px]" alt="">

                    <div class="mt-4 space-y-2">
                        <template x-for="option in question.options" :key="option.id">
                            <label
                                class="flex cursor-pointer items-start gap-3 rounded-[8px] border p-3 text-sm text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-gray-700/50"
                                :class="answers[question.id] === option.id
                                    ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                                    : 'border-gray-200 dark:border-gray-700'"
                            >
                                <input
                                    type="radio"
                                    :name="'q' + question.id"
                                    :value="option.id"
                                    :checked="answers[question.id] === option.id"
                                    x-on:change="select(question.id, option.id)"
                                    class="mt-0.5 w-4 shrink-0 text-primary-600"
                                >
                                <span class="leading-[1.6]">
                                    <span class="font-semibold" x-text="option.label + '.'"></span>
                                    <span x-text="option.text"></span>
                                </span>
                            </label>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Palette. --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="mb-3 flex flex-wrap items-center gap-x-4 gap-y-1.5">
                <span class="flex items-center gap-1.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                    <span class="h-3 w-3 rounded-[4px] bg-primary-600"></span> Current
                </span>
                <span class="flex items-center gap-1.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                    <span class="h-3 w-3 rounded-[4px] bg-primary-100 dark:bg-primary-900/40"></span> Answered
                </span>
                <span class="flex items-center gap-1.5 text-[11px] font-semibold text-gray-500 dark:text-gray-400">
                    <span class="h-3 w-3 rounded-[4px] bg-gray-100 dark:bg-gray-700"></span> Not answered
                </span>
            </div>

            <div class="grid grid-cols-8 gap-1.5 sm:grid-cols-10 lg:grid-cols-12">
                <template x-for="(question, index) in questions" :key="question.id">
                    <button
                        type="button"
                        x-on:click="go(index)"
                        class="flex h-8 w-8 items-center justify-center rounded-[6px] text-xs font-bold transition-colors duration-150"
                        :class="current === index
                            ? 'bg-primary-600 text-white'
                            : (isAnswered(question.id)
                                ? 'bg-primary-100 text-primary-700 dark:bg-primary-900/40 dark:text-primary-300'
                                : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400')"
                        :aria-label="`Question ${question.number}${isAnswered(question.id) ? ', answered' : ', not answered'}`"
                        x-text="question.number"
                    ></button>
                </template>
            </div>
        </div>

        {{-- Navigation. Submit stays reachable throughout, so a student who has
             finished early is not made to page to the end to hand in. --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <button
                type="button"
                x-on:click="prev()"
                :disabled="current === 0"
                class="flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-4 py-2.5 text-sm font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:translate-y-0 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-chevron-left text-[11px]"></i>
                Previous
            </button>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    x-on:click="confirmSubmit()"
                    class="rounded-[8px] bg-green-600 px-5 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-green-700 hover:shadow-md"
                >
                    Submit Test
                </button>

                <button
                    type="button"
                    x-show="current < questions.length - 1"
                    x-on:click="next()"
                    class="flex items-center gap-1.5 rounded-[8px] bg-primary-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-primary-700 hover:shadow-md"
                >
                    Next
                    <i class="fa-solid fa-chevron-right text-[11px]"></i>
                </button>
            </div>
        </div>

        <form :action="submitUrl" method="POST" x-ref="submitForm" class="hidden">
            @csrf
        </form>
    </div>
</x-student-layout>
