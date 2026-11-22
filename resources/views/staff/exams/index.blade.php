{{-- The class teacher's score sheet.

     Pick a class and every subject it takes is on the page, each with a Test
     box and an Examination box for every student, saved together with one
     button. A subject teacher sees only the subjects they teach.

     Built for a phone first: each student is a row with the two boxes side by
     side under their name, rather than an eight-column table squeezed into a
     narrow screen.

     Only Test and Examination are ever typed. Total and grade are worked out
     and shown live as a preview; the server recalculates them from the two
     numbers submitted. --}}
@php
    use App\Models\GradeBand;

    $bands = ($school->gradeBands->isNotEmpty() ? $school->gradeBands : collect(GradeBand::defaultBands()))
        ->map(fn ($band) => [
            'min' => (float) (is_array($band) ? $band['min_percent'] : $band->min_percent),
            'max' => (float) (is_array($band) ? $band['max_percent'] : $band->max_percent),
            'letter' => is_array($band) ? $band['letter'] : $band->letter,
        ])
        ->values();

    $examLabel = fn ($option) => $option->term->label().' · '.$option->session;
@endphp
<x-staff-layout page-title="Test/Exam Score" page-subtitle="Enter and update Test and Examination scores for your class.">
    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-[10px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[10px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-300" role="alert">
                <p class="font-semibold">Some scores were not saved. Please check:</p>
                <ul class="mt-1 list-disc pl-5">
                    @foreach (collect($errors->all())->unique()->take(8) as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- The scoring structure, stated once. --}}
        <div class="flex flex-wrap items-center gap-x-6 gap-y-2 rounded-[10px] border border-gray-200 bg-white px-5 py-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <p class="text-[13px] font-bold text-gray-900 dark:text-white">Scoring structure</p>
            <div class="flex flex-wrap items-center gap-2 text-[13px]">
                <span class="rounded-[6px] bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">Test <span class="text-gray-400">max</span> 40</span>
                <span class="text-gray-400">+</span>
                <span class="rounded-[6px] bg-gray-100 px-2.5 py-1 font-semibold text-gray-700 dark:bg-gray-700 dark:text-gray-200">Examination <span class="text-gray-400">max</span> 60</span>
                <span class="text-gray-400">=</span>
                <span class="rounded-[6px] bg-primary-50 px-2.5 py-1 font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">Total 100</span>
            </div>
            <small class="field-hint">Total and grade are calculated by the system.</small>
        </div>

        @if (! $hasRows)
            <div class="rounded-[10px] border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                @if (! $hasAssignments)
                    You have not been assigned to a class or subject yet, so there are no
                    scores for you to enter. Ask your school administrator to assign you.
                @else
                    No subjects are set up for your class yet, so there is nothing to score.
                    Ask your school administrator to check the class's subjects.
                @endif
            </div>
        @else
            {{-- Class, term and subject. Changing one reloads the sheet. --}}
            <form method="GET" class="grid grid-cols-1 gap-3 rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:grid-cols-3">
                <div>
                    <label for="filter_class" class="field-label">Class</label>
                    <select id="filter_class" name="class_name" onchange="this.form.examination.value = ''; this.form.subject.value = ''; this.form.submit()" class="mt-1 w-full">
                        @foreach ($classOptions as $className)
                            <option value="{{ $className }}" @selected($selectedClass === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter_examination" class="field-label">Term</label>
                    <select id="filter_examination" name="examination" onchange="this.form.submit()" class="mt-1 w-full">
                        @foreach ($examinationOptions as $option)
                            <option value="{{ $option->uuid }}" @selected($examination?->is($option))>{{ $examLabel($option) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter_subject" class="field-label">Subject</label>
                    <select id="filter_subject" name="subject" onchange="this.form.submit()" class="mt-1 w-full">
                        <option value="">All subjects ({{ $subjectOptions->count() }})</option>
                        @foreach ($subjectOptions as $subjectName)
                            <option value="{{ $subjectName }}" @selected($selectedSubject === $subjectName)>{{ $subjectName }}</option>
                        @endforeach
                    </select>
                </div>
            </form>

            @if ($examination && $students->isEmpty())
                <div class="rounded-[10px] border border-gray-200 bg-white px-5 py-10 text-center text-sm text-gray-500 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">
                    There are no active students in {{ $examination->class_name }} yet. Once students are added to the class, their score boxes appear here.
                </div>
            @elseif ($examination)
                {{-- Jump to a subject. --}}
                @if ($subjects->count() > 1)
                    <nav class="-mx-1 flex gap-2 overflow-x-auto px-1 pb-1" aria-label="Subjects">
                        @foreach ($subjects as $subject)
                            @php($entered = ($existing->get($subject->id) ?? collect())->count())
                            <a href="#subject-{{ $subject->id }}" class="flex shrink-0 items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:border-primary-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
                                {{ $subject->name }}
                                <span @class([
                                    'rounded-full px-1.5 text-[10px] font-bold',
                                    'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400' => $entered >= $students->count(),
                                    'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => $entered < $students->count(),
                                ])>{{ $entered }}/{{ $students->count() }}</span>
                            </a>
                        @endforeach
                    </nav>
                @endif

                <form
                    method="POST"
                    action="{{ route('staff.exams.class-scores.update', [$school, $examination]) }}"
                    class="space-y-5"
                    x-data="{
                        bands: @js($bands),
                        grade(test, exam, max) {
                            if ((test === null || test === '') && (exam === null || exam === '')) return '–'
                            const percentage = max > 0 ? ((Number(test) || 0) + (Number(exam) || 0)) / max * 100 : 0
                            return this.bands.find((band) => percentage >= band.min && percentage < band.max + 1)?.letter ?? '–'
                        },
                        total(test, exam) {
                            if ((test === null || test === '') && (exam === null || exam === '')) return '–'
                            return Math.round(((Number(test) || 0) + (Number(exam) || 0)) * 100) / 100
                        },
                        pack(event) {
                            {{-- Sent as one field: a full class sheet has more boxes than PHP accepts as separate fields. --}}
                            const scores = {}
                            this.$el.querySelectorAll('input[data-score]').forEach((input) => {
                                const { subject, student, kind } = input.dataset
                                scores[subject] ??= {}
                                scores[subject][student] ??= {}
                                scores[subject][student][kind] = input.value === '' ? null : input.value
                                input.removeAttribute('name')
                            })
                            this.$refs.json.value = JSON.stringify(scores)
                        },
                    }"
                    @submit="pack($event)"
                >
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="scores_json" x-ref="json">

                    @foreach ($subjects as $subject)
                        @php($subjectScores = $existing->get($subject->id) ?? collect())
                        <section id="subject-{{ $subject->id }}" class="scroll-mt-24 overflow-hidden rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                            <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                                <h2 class="text-sm font-bold text-gray-900 dark:text-white">
                                    <span class="text-gray-400">{{ $loop->iteration }}.</span> {{ $subject->name }}
                                </h2>
                                <small class="text-xs text-gray-500 dark:text-gray-400">
                                    Test /{{ $subject->testMaxScore() }} · Exam /{{ $subject->examMaxScore() }} · {{ $subjectScores->count() }}/{{ $students->count() }} scored
                                </small>
                            </header>

                            {{-- Column labels, for wider screens. On a phone each box carries its own label. --}}
                            <div class="hidden grid-cols-[2.5rem_1fr_7rem_7rem_4rem_3rem] gap-3 px-4 pt-3 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:grid">
                                <span>S/N</span><span>Student / Pupil</span><span>Test /{{ $subject->testMaxScore() }}</span><span>Exam /{{ $subject->examMaxScore() }}</span><span>Total</span><span>Grade</span>
                            </div>

                            <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                                @foreach ($students as $student)
                                    @php($score = $subjectScores->get($student->id))
                                    <li
                                        class="grid grid-cols-[1.75rem_1fr] gap-x-3 gap-y-2 px-4 py-3 sm:grid-cols-[2.5rem_1fr_7rem_7rem_4rem_3rem] sm:items-center"
                                        x-data="{ test: @js($score?->test_score !== null ? (float) $score->test_score : ''), exam: @js($score?->exam_score !== null ? (float) $score->exam_score : '') }"
                                    >
                                        <span class="pt-0.5 text-xs tabular-nums text-gray-400 sm:pt-0">{{ $loop->iteration }}</span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</p>
                                            <small class="text-xs text-gray-500 dark:text-gray-400">{{ $student->admission_number }}</small>
                                        </div>

                                        {{-- On a phone: Test, Exam, Total and Grade in one row under the name. --}}
                                        <div class="col-span-2 grid grid-cols-[1fr_1fr_auto] gap-2 sm:contents">
                                            <label class="block">
                                                <span class="mb-1 block text-[11px] font-semibold text-gray-500 dark:text-gray-400 sm:hidden">Test /{{ $subject->testMaxScore() }}</span>
                                                <input
                                                    type="number"
                                                    inputmode="decimal"
                                                    name="scores[{{ $subject->id }}][{{ $student->id }}][test]"
                                                    data-score data-subject="{{ $subject->id }}" data-student="{{ $student->id }}" data-kind="test"
                                                    x-model="test"
                                                    value="{{ $score?->test_score !== null ? (float) $score->test_score : '' }}"
                                                    min="0" max="{{ $subject->testMaxScore() }}" step="0.01"
                                                    placeholder="0–{{ $subject->testMaxScore() }}"
                                                    class="h-11 w-full rounded-[8px] border border-gray-300 bg-white px-3 text-center text-base font-semibold text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                                    aria-label="{{ $subject->name }} test score for {{ $student->fullName() }}"
                                                >
                                            </label>
                                            <label class="block">
                                                <span class="mb-1 block text-[11px] font-semibold text-gray-500 dark:text-gray-400 sm:hidden">Exam /{{ $subject->examMaxScore() }}</span>
                                                <input
                                                    type="number"
                                                    inputmode="decimal"
                                                    name="scores[{{ $subject->id }}][{{ $student->id }}][exam]"
                                                    data-score data-subject="{{ $subject->id }}" data-student="{{ $student->id }}" data-kind="exam"
                                                    x-model="exam"
                                                    value="{{ $score?->exam_score !== null ? (float) $score->exam_score : '' }}"
                                                    min="0" max="{{ $subject->examMaxScore() }}" step="0.01"
                                                    placeholder="0–{{ $subject->examMaxScore() }}"
                                                    class="h-11 w-full rounded-[8px] border border-gray-300 bg-white px-3 text-center text-base font-semibold text-gray-900 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
                                                    aria-label="{{ $subject->name }} examination score for {{ $student->fullName() }}"
                                                >
                                            </label>
                                            <div class="flex items-end gap-3 pb-2.5 text-sm sm:contents">
                                                <span class="font-bold tabular-nums text-gray-900 dark:text-white sm:pb-0">
                                                    <span class="text-[11px] font-semibold text-gray-500 sm:hidden">Total </span><span x-text="total(test, exam)">{{ $score ? (float) $score->score : '–' }}</span>
                                                </span>
                                                <span class="font-bold text-primary-700 dark:text-primary-300" x-text="grade(test, exam, {{ (float) $subject->max_score }})">–</span>
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach

                    {{-- Stays in reach while scrolling, above the phone's bottom menu. --}}
                    <div class="sticky bottom-20 z-20 flex flex-wrap items-center justify-between gap-3 rounded-[10px] border border-gray-200 bg-white/95 p-4 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-800/95">
                        <small class="field-hint hidden sm:block">Leave both boxes blank to skip a student. Scores already saved stay as they are.</small>
                        <button type="submit" class="btn w-full rounded-[8px] bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700 sm:w-auto">
                            <i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i>
                            Save all scores
                        </button>
                    </div>
                </form>
            @endif
        @endif
    </div>
</x-staff-layout>
