@php
    $totalScore = $subjects->sum(fn ($subject) => (float) ($subject->scores->first()?->score ?? 0));
    $totalMax = $subjects->sum('max_score');
    $overallGrade = $summary['average'] === null ? 'N/A' : \App\Models\GradeBand::resolve($school, $summary['average']);
    $position = $summary['position'] ? $summary['position'].($summary['position'] === 1 ? 'st' : ($summary['position'] === 2 ? 'nd' : ($summary['position'] === 3 ? 'rd' : 'th'))) : 'N/A';

    // The same best-to-worst colours as the report card, by the grade band's
    // rank, so a grade reads the same here as on the printed card.
    $gradeKey = ($school->gradeBands->isNotEmpty()
        ? $school->gradeBands->sortBy('position')->map(fn ($band) => ['min_percent' => $band->min_percent, 'max_percent' => $band->max_percent])
        : collect(\App\Models\GradeBand::defaultBands()))->values();
    $gradeColors = ['#15803d', '#1d4ed8', '#b45309', '#c2410c', '#b91c1c'];
    $gradeColor = function (?float $percentage) use ($gradeKey, $gradeColors) {
        if ($percentage === null) {
            return '#6b7280';
        }

        $index = $gradeKey->search(fn ($band) => \App\Models\GradeBand::covers((int) $band['min_percent'], (int) $band['max_percent'], $percentage));

        return $gradeColors[($index === false ? $gradeKey->count() - 1 : $index) % count($gradeColors)];
    };
@endphp

<div class="space-y-4 text-left">
    {{-- Who and when: the pupil, then four small tiles. --}}
    <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center gap-3 border-b border-gray-100 p-3 dark:border-gray-700">
            @if ($student->photoUrl())
                <img data-fallback="{{ $student->initials() }}" src="{{ $student->photoUrl() }}" class="h-11 w-11 shrink-0 rounded-full object-cover">
            @else
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-100 text-sm font-bold text-primary-700 dark:bg-primary-900/30 dark:text-primary-300">{{ $student->initials() }}</span>
            @endif
            <div class="min-w-0">
                <p class="truncate text-[15px] font-bold leading-tight text-gray-900 dark:text-white">{{ $student->fullName() }}</p>
                {{-- On every result, in every portal: the admission number is
                     how a school tells two pupils of the same name apart. --}}
                <p class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-xs">
                    <span class="font-semibold text-gray-600 dark:text-gray-300">Admission No.</span>
                    <span class="rounded-[5px] bg-gray-100 px-1.5 py-0.5 font-mono text-[11px] font-semibold text-gray-800 dark:bg-gray-700 dark:text-gray-100">{{ $student->admission_number ?: 'N/A' }}</span>
                </p>
            </div>
        </div>
        <dl class="grid grid-cols-2 gap-px bg-gray-100 text-sm dark:bg-gray-700 sm:grid-cols-4">
            <div class="bg-white px-3 py-2.5 dark:bg-gray-800"><dt class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Class</dt><dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $examination->class_name }}</dd></div>
            <div class="bg-white px-3 py-2.5 dark:bg-gray-800"><dt class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Session</dt><dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $examination->session }}</dd></div>
            <div class="bg-white px-3 py-2.5 dark:bg-gray-800"><dt class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Term</dt><dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $examination->term->label() }}</dd></div>
            <div class="bg-white px-3 py-2.5 dark:bg-gray-800"><dt class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Position</dt><dd class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $position }}</dd></div>
        </dl>
    </div>

    {{-- The headline figures. --}}
    @if ($subjects->isNotEmpty())
        <div class="grid grid-cols-3 gap-2">
            <div class="rounded-[10px] border border-gray-200 bg-white px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Total</p>
                <p class="mt-0.5 text-[15px] font-bold tabular-nums text-gray-900 dark:text-white">{{ \App\Support\Mark::format($totalScore) }}<span class="text-xs font-semibold text-gray-600 dark:text-gray-300">/{{ $totalMax }}</span></p>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Average</p>
                <p class="mt-0.5 text-[15px] font-bold tabular-nums text-gray-900 dark:text-white">{{ $summary['average'] !== null ? $summary['average'].'%' : 'N/A' }}</p>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white px-3 py-2.5 dark:border-gray-700 dark:bg-gray-800">
                <p class="text-[11px] font-medium text-gray-600 dark:text-gray-300">Grade</p>
                <p class="mt-0.5"><span class="inline-flex min-w-[28px] justify-center rounded-[6px] px-2 py-0.5 text-[13px] font-bold text-white" style="background-color: {{ $gradeColor($summary['average']) }};">{{ $overallGrade }}</span></p>
            </div>
        </div>
    @endif

    {{-- The subjects. A list of rows on a phone, where four columns would
         break every heading in half; the table from a tablet up. --}}
    <div class="overflow-hidden rounded-[10px] border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800">
        <div class="flex items-center justify-between border-b border-gray-100 bg-gray-50 px-3 py-2 dark:border-gray-700 dark:bg-gray-900/40">
            <p class="text-xs font-bold uppercase tracking-wide text-gray-700 dark:text-gray-200">Subjects</p>
            <p class="text-[11px] font-medium text-gray-600 dark:text-gray-300">{{ $subjects->count() }} {{ Str::plural('subject', $subjects->count()) }}</p>
        </div>

        @if ($subjects->isEmpty())
            <p class="px-3 py-6 text-center text-sm font-semibold text-gray-700 dark:text-gray-300">No subjects recorded for this examination yet.</p>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-gray-700 sm:hidden">
                @foreach ($subjects as $subject)
                    @php $score = $subject->scores->first(); @endphp
                    <li class="flex items-center gap-3 px-3 py-2.5">
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold leading-snug text-gray-900 dark:text-white">{{ $subject->name }}</p>
                            <p class="mt-0.5 text-xs tabular-nums text-gray-600 dark:text-gray-300">
                                {{ $score ? \App\Support\Mark::format($score->score) : 'N/A' }} / {{ $subject->max_score }}
                                @if ($score?->test_score !== null && $score?->exam_score !== null)
                                    &middot; Test {{ \App\Support\Mark::format($score->test_score) }} + Exam {{ \App\Support\Mark::format($score->exam_score) }}
                                @endif
                            </p>
                        </div>
                        <span class="shrink-0 text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ $score ? $score->percentage().'%' : 'N/A' }}</span>
                        <span class="inline-flex min-w-[28px] shrink-0 justify-center rounded-[6px] px-1.5 py-0.5 text-xs font-bold text-white" style="background-color: {{ $gradeColor($score?->percentage()) }};">{{ $score ? $score->grade() : 'N/A' }}</span>
                    </li>
                @endforeach
            </ul>

            <div class="hidden sm:block">
            <table class="result-subjects w-full text-left text-sm">
                <thead class="text-[11px] font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                    <tr class="border-b border-gray-100 dark:border-gray-700">
                        <th class="px-3 py-2 font-semibold">Subject</th>
                        <th class="px-3 py-2 font-semibold">Score</th>
                        <th class="px-3 py-2 text-right font-semibold">Percentage</th>
                        <th class="px-3 py-2 text-center font-semibold">Grade</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @foreach ($subjects as $subject)
                        @php $score = $subject->scores->first(); @endphp
                        <tr>
                            <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white">{{ $subject->name }}</td>
                            <td class="px-3 py-2 tabular-nums text-gray-900 dark:text-gray-200">
                                <span class="font-semibold">{{ $score ? \App\Support\Mark::format($score->score) : 'N/A' }} / {{ $subject->max_score }}</span>
                                @if ($score?->test_score !== null && $score?->exam_score !== null)
                                    <span class="ml-1 text-[11px] text-gray-600 dark:text-gray-300">(T{{ \App\Support\Mark::format($score->test_score) }}+E{{ \App\Support\Mark::format($score->exam_score) }})</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-semibold tabular-nums text-gray-900 dark:text-gray-200">{{ $score ? $score->percentage().'%' : 'N/A' }}</td>
                            <td class="px-3 py-2 text-center"><span class="inline-flex min-w-[28px] justify-center rounded-[6px] px-1.5 py-0.5 text-xs font-bold text-white" style="background-color: {{ $gradeColor($score?->percentage()) }};">{{ $score ? $score->grade() : 'N/A' }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="border-t border-gray-200 bg-gray-50 text-sm font-bold text-gray-900 dark:border-gray-700 dark:bg-gray-900/40 dark:text-white">
                    <tr>
                        <td class="px-3 py-2">Total</td>
                        <td class="px-3 py-2 tabular-nums">{{ \App\Support\Mark::format($totalScore) }} / {{ $totalMax }}</td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ $summary['average'] !== null ? $summary['average'].'%' : 'N/A' }}</td>
                        <td class="px-3 py-2 text-center">{{ $overallGrade }}</td>
                    </tr>
                </tfoot>
            </table>
            </div>
        @endif
    </div>

    <div class="rounded-[10px] border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
        <small class="field-hint">
            Attendance This Term
            @if (($attendance['source'] ?? null) === 'manual')
                &middot; entered as term totals
            @endif
        </small>
        @if ($attendance)
            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-white">
                Present {{ $attendance['present'] }} &middot; Absent {{ $attendance['absent'] }} &middot; Late {{ $attendance['late'] }} &middot; Excused {{ $attendance['excused'] }}
                @if ($attendance['percent'] !== null) &middot; {{ $attendance['percent'] }}% @endif
            </p>
        @else
            <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-gray-200">Term dates not set</p>
        @endif
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <div>
            <label for="result-teacher-remark" class="field-label font-bold uppercase text-gray-900 dark:text-gray-100">Class Teacher's Remark</label>
            @if ($canEditTeacherRemark)
                <textarea id="result-teacher-remark" rows="2" class="mt-1 w-full">{{ $report->teacher_remark }}</textarea>
            @else
                <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-200">{{ $report->teacher_remark ?: 'N/A' }}</p>
            @endif
        </div>
        <div>
            <label for="result-principal-remark" class="field-label font-bold uppercase text-gray-900 dark:text-gray-100">Principal's Remark</label>
            @if ($canEditPrincipalRemark)
                {{-- Pick a saved one OR write a new one. Choosing from the
                     library fills the box rather than locking it, so a
                     Principal can start from a saved sentence and adjust it
                     for the pupil in front of them, which is what most
                     remarks actually are. --}}
                @if (($principalRemarkLibrary ?? collect())->isNotEmpty())
                    <select
                        id="result-principal-remark-library"
                        class="mt-1 w-full text-sm"
                        onchange="if (this.value) { document.getElementById('result-principal-remark').value = this.value; }"
                        aria-label="Insert a saved remark"
                    >
                        <option value="">Insert a saved remark&hellip;</option>
                        @foreach ($principalRemarkLibrary as $saved)
                            <option value="{{ $saved->body }}">{{ Str::limit($saved->body, 70) }}</option>
                        @endforeach
                    </select>
                @endif

                <textarea id="result-principal-remark" rows="2" class="mt-1 w-full">{{ $report->principal_remark }}</textarea>

                <label class="mt-1.5 flex items-start gap-2 text-xs font-semibold text-gray-900 dark:text-gray-200">
                    <input type="checkbox" id="result-save-principal-remark" class="mt-0.5 rounded">
                    Save this remark to my library for next time
                </label>
            @else
                <p class="mt-1 text-sm font-semibold italic text-gray-900 dark:text-gray-200">{{ $report->principal_remark ?: 'N/A' }}</p>
            @endif
        </div>
    </div>
    @if ($canEditTeacherRemark || $canEditPrincipalRemark)
        <div class="flex justify-end">
            <button type="button" onclick="window.resultPreviewSaveRemarks()" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-xs font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700"><i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save Remarks</button>
        </div>
    @endif
</div>
