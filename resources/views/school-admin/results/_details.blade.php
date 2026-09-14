@php
    $totalScore = $subjects->sum(fn ($subject) => (float) ($subject->scores->first()?->score ?? 0));
    $totalMax = $subjects->sum('max_score');
    $overallGrade = $summary['average'] === null ? 'N/A' : \App\Models\GradeBand::resolve($school, $summary['average']);
@endphp

<div class="space-y-5 text-left">
    <div class="grid grid-cols-2 gap-3 rounded-[8px] bg-gray-50 p-3 text-sm dark:bg-gray-900/40 sm:grid-cols-3">
        <div><p class="field-hint">Student</p><p class="font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</p></div>
        {{-- Withheld from the Class Teacher portal, which passes false. A
             pupil's admission number is their Student ID and the teacher's
             report card page is not where it belongs. --}}
        @if ($showStudentId ?? true)
            <div><p class="field-hint">Student ID</p><p class="font-semibold text-gray-900 dark:text-white">{{ $student->admission_number }}</p></div>
        @endif
        <div><p class="field-hint">Class</p><p class="font-semibold text-gray-900 dark:text-white">{{ $examination->class_name }}</p></div>
        <div><p class="field-hint">Session</p><p class="font-semibold text-gray-900 dark:text-white">{{ $examination->session }}</p></div>
        <div><p class="field-hint">Term</p><p class="font-semibold text-gray-900 dark:text-white">{{ $examination->term->label() }}</p></div>
        <div><p class="field-hint">Position</p><p class="font-semibold text-gray-900 dark:text-white">{{ $summary['position'] ? $summary['position'].($summary['position'] === 1 ? 'st' : ($summary['position'] === 2 ? 'nd' : ($summary['position'] === 3 ? 'rd' : 'th'))) : 'N/A' }}</p></div>
    </div>

    <div class="overflow-x-auto rounded-[8px] border border-gray-200 dark:border-gray-700">
        <table class="w-full text-left text-sm">
            <thead class="bg-gray-50 text-xs font-bold uppercase text-gray-900 dark:bg-gray-900/40 dark:text-gray-200">
                <tr>
                    <th class="px-3 py-2 font-semibold">Subject</th>
                    <th class="px-3 py-2 font-semibold">Score</th>
                    <th class="px-3 py-2 font-semibold">Percentage</th>
                    <th class="px-3 py-2 font-semibold">Grade</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($subjects as $subject)
                    @php $score = $subject->scores->first(); @endphp
                    <tr>
                        <td class="px-3 py-2 font-semibold text-gray-900 dark:text-white">{{ $subject->name }}</td>
                        <td class="px-3 py-2 font-semibold text-gray-900 dark:text-gray-200">
                            {{ $score ? rtrim(rtrim($score->score, '0'), '.') : 'N/A' }} / {{ $subject->max_score }}
                            @if ($score?->test_score !== null && $score?->exam_score !== null)
                                <span class="text-[10px] font-semibold text-gray-700 dark:text-gray-300">(T{{ rtrim(rtrim($score->test_score, '0'), '.') }}+E{{ rtrim(rtrim($score->exam_score, '0'), '.') }})</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 font-semibold text-gray-900 dark:text-gray-200">{{ $score ? $score->percentage().'%' : 'N/A' }}</td>
                        <td class="px-3 py-2 font-semibold text-gray-900 dark:text-gray-200">{{ $score ? $score->grade() : 'N/A' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-3 py-6 text-center font-semibold text-gray-700 dark:text-gray-300">No subjects recorded for this examination yet.</td></tr>
                @endforelse
            </tbody>
            @if ($subjects->isNotEmpty())
                <tfoot class="border-t border-gray-200 bg-gray-50 text-sm font-bold text-gray-900 dark:border-gray-700 dark:bg-gray-900/40 dark:text-white">
                    <tr>
                        <td class="px-3 py-2">Total</td>
                        <td class="px-3 py-2">{{ rtrim(rtrim((string) $totalScore, '0'), '.') }} / {{ $totalMax }}</td>
                        <td class="px-3 py-2">{{ $summary['average'] !== null ? $summary['average'].'%' : 'N/A' }}</td>
                        <td class="px-3 py-2">{{ $overallGrade }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>

    <div class="rounded-[8px] bg-gray-50 p-3 dark:bg-gray-900/40">
        <p class="field-hint">Attendance This Term</p>
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
            <button type="button" onclick="window.resultPreviewSaveRemarks()" class="rounded-[8px] bg-blue-600 px-4 py-2 text-xs font-semibold text-white transition-all duration-200 hover:-translate-y-0.5 hover:bg-blue-700">Save Remarks</button>
        </div>
    @endif
</div>
