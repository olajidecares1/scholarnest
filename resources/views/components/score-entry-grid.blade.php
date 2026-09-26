{{-- Score entry for one subject, one class.

     Test and Exam are the only things typed. Everything to the right of them,
     Total, Percentage, Grade, Remark, is worked out, shown live so the
     teacher can see what they are producing, and never editable. That is the
     point rather than a convenience: a grade somebody could type is a grade
     that can disagree with the marks it is supposed to come from.

     The live figures are a preview. The values that count are recalculated on
     the server from the two numbers actually submitted, against this school's
     own grade bands, the browser is showing its working, not deciding it.

     Shared by the School Admin's page and the teacher's, because a teacher and
     an administrator entering the same marks must produce the same result. --}}
@props([
    'school',
    'examination',
    'subject',
    'students',
    'existing',
    'action',
    'subjectUrl',

    // The School Admin route takes a POST and the teacher's takes a PUT.
    // One grid, so the caller says which.
    'method' => 'POST',
])

@php
    use App\Models\GradeBand;

    // This school's own bands, in the order it arranged them, never a
    // hard-coded scale. One school's C is another school's B.
    $bands = ($school->gradeBands->isNotEmpty()
        ? $school->gradeBands
        : collect(GradeBand::defaultBands()))
        ->map(fn ($band) => [
            'min' => (float) (is_array($band) ? $band['min_percent'] : $band->min_percent),
            'max' => (float) (is_array($band) ? $band['max_percent'] : $band->max_percent),
            'letter' => is_array($band) ? $band['letter'] : $band->letter,
            'description' => is_array($band) ? ($band['description'] ?? '') : ($band->description ?? ''),
        ])
        ->values();
@endphp

<div
    x-data="{
        bands: @js($bands),
        max: {{ (float) $subject->max_score }},
        percentage(test, exam) {
            if (test === null && exam === null) {
                return null
            }

            const total = (Number(test) || 0) + (Number(exam) || 0)

            return this.max > 0 ? (total / this.max) * 100 : null
        },
        band(test, exam) {
            const percentage = this.percentage(test, exam)

            if (percentage === null) {
                return null
            }

            return this.bands.find((band) => percentage >= band.min && percentage <= band.max) ?? null
        },
    }"
>
    {{-- Subject by subject, without going back to the examination first. The
         teacher finishes one, picks the next, and carries on. --}}
    @if ($examination->subjects->count() > 1)
        <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-700">
            <small class="field-hint">Subject</small>
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($examination->subjects as $option)
                    @php($entered = $option->scores()->count())
                    <a
                        href="{{ $subjectUrl($option) }}"
                        @class([
                            'flex items-center gap-2 rounded-[8px] border px-3 py-1.5 text-xs font-semibold transition',
                            'border-primary-500 bg-primary-50 text-primary-700 dark:bg-primary-900/30 dark:text-primary-300' => $option->is($subject),
                            'border-gray-200 text-gray-600 hover:border-gray-300 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-700/50' => ! $option->is($subject),
                        ])
                    >
                        {{ $option->name }}
                        <span @class([
                            'rounded-full px-1.5 text-[10px] font-bold',
                            'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400' => $entered >= $students->count() && $students->isNotEmpty(),
                            'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400' => $entered < $students->count() || $students->isEmpty(),
                        ])>{{ $entered }}/{{ $students->count() }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <form method="POST" action="{{ $action }}">
        @csrf
        @if (strtoupper($method) !== 'POST')
            @method($method)
        @endif

        <div class="overflow-x-auto">
            <table class="w-full min-w-[46rem] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                    <tr>
                        <th class="px-6 py-3 font-semibold">Student / Pupil</th>
                        <th class="px-4 py-3 font-semibold">Test / {{ $subject->testMaxScore() }}</th>
                        <th class="px-4 py-3 font-semibold">Exam / {{ $subject->examMaxScore() }}</th>
                        <th class="px-4 py-3 font-semibold">Total / {{ (int) $subject->max_score }}</th>
                        <th class="px-4 py-3 font-semibold">%</th>
                        <th class="px-4 py-3 font-semibold">Grade</th>
                        <th class="px-4 py-3 font-semibold">Remark</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($students as $student)
                        @php($score = $existing->get($student->id))
                        <tr
                            class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700"
                            x-data="{ test: {{ $score?->test_score ?? 'null' }}, exam: {{ $score?->exam_score ?? 'null' }} }"
                        >
                            <td class="px-6 py-3">
                                <p class="font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</p>
                                <small class="field-hint">{{ $student->admission_number }}</small>
                            </td>

                            <td class="px-4 py-3">
                                <input
                                    type="number"
                                    name="test_scores[{{ $student->id }}]"
                                    x-model.number="test"
                                    min="0"
                                    max="{{ $subject->testMaxScore() }}"
                                    step="0.01"
                                    class="w-24"
                                    aria-label="Test score for {{ $student->fullName() }}"
                                >
                            </td>

                            <td class="px-4 py-3">
                                <input
                                    type="number"
                                    name="exam_scores[{{ $student->id }}]"
                                    x-model.number="exam"
                                    min="0"
                                    max="{{ $subject->examMaxScore() }}"
                                    step="0.01"
                                    class="w-24"
                                    aria-label="Exam score for {{ $student->fullName() }}"
                                >
                            </td>

                            {{-- Not an input. Nothing below is. --}}
                            <td class="px-4 py-3 font-bold text-gray-900 dark:text-white" x-text="test === null && exam === null ? 'N/A' : (Number(test) || 0) + (Number(exam) || 0)"></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300" x-text="percentage(test, exam) === null ? 'N/A' : percentage(test, exam).toFixed(1) + '%'"></td>
                            <td class="px-4 py-3 font-bold text-gray-900 dark:text-white" x-text="band(test, exam)?.letter ?? 'N/A'"></td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300" x-text="band(test, exam)?.description || 'N/A'"></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                No active students in {{ $examination->class_name }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($students->isNotEmpty())
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 p-6 dark:border-gray-700">
                <small class="field-hint">
                    Total, percentage, grade and remark are worked out from the two scores and cannot be typed.
                    Leave both blank to skip a student.
                </small>

                <button type="submit" class="btn rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
                    Save Scores
                </button>
            </div>
        @endif
    </form>
</div>
