@php
    $brandPrimary = $school->website?->brand_primary_color ?? '#1d4ed8';
    $brandSecondary = $school->website?->brand_secondary_color ?? '#111a35';
    $motto = $school->website?->slogan_tagline ?: $school->website?->slogan;

    $totalScore = $subjects->sum(fn ($subject) => (float) ($subject->scores->first()?->score ?? 0));
    $totalTest = $subjects->sum(fn ($subject) => (float) ($subject->scores->first()?->test_score ?? 0));
    $totalExam = $subjects->sum(fn ($subject) => (float) ($subject->scores->first()?->exam_score ?? 0));
    $totalMax = $subjects->sum('max_score');
    $testMaxLabel = $subjects->first()?->testMaxScore() ?? 40;
    $examMaxLabel = $subjects->first()?->examMaxScore() ?? 60;
    $subjectMaxLabel = $subjects->first()?->max_score ?? 100;

    $overallGrade = $summary['average'] === null ? '—' : \App\Models\GradeBand::resolve($school, $summary['average']);
    $overallDescription = $summary['average'] === null ? null : \App\Models\GradeBand::describe($school, $summary['average']);

    $position = $summary['position'] ?? null;
    $positionLabel = $position ? $position.match (true) {
        in_array($position % 100, [11, 12, 13]) => 'th',
        $position % 10 === 1 => 'st',
        $position % 10 === 2 => 'nd',
        $position % 10 === 3 => 'rd',
        default => 'th',
    } : '—';

    $gradeKey = ($school->gradeBands->isNotEmpty()
        ? $school->gradeBands->sortBy('position')->map(fn ($band) => ['min_percent' => $band->min_percent, 'max_percent' => $band->max_percent, 'letter' => $band->letter, 'description' => $band->description])
        : collect(\App\Models\GradeBand::defaultBands()))->values();

    // A best-to-worst color scale (green through red) applied by the grade
    // band's rank rather than its letter, since a school's configured bands
    // can use any letters/count - this keeps the "green = best" visual
    // language from the reference design working for any grading scale.
    $gradeColors = ['#16a34a', '#2563eb', '#d97706', '#ea580c', '#dc2626'];
    $colorForPercentage = function (?float $percentage) use ($gradeKey, $gradeColors) {
        if ($percentage === null) {
            return '#6b7280';
        }

        $index = $gradeKey->search(fn ($band) => $percentage >= $band['min_percent'] && $percentage <= $band['max_percent']);
        $index = $index === false ? $gradeKey->count() - 1 : $index;

        return $gradeColors[$index % count($gradeColors)];
    };
    $overallColor = $colorForPercentage($summary['average'] ?? null);

    $websiteHost = $school->resolvedPublicHost();
@endphp

<div class="mx-auto w-full max-w-3xl overflow-hidden rounded-[5px] bg-white text-left text-gray-900" style="aspect-ratio: 1 / 1.4142; font-family: 'Inter', sans-serif; border: 2.5px solid {{ $brandSecondary }};">
    <div class="flex h-full flex-col overflow-hidden">
        <div class="flex-1 space-y-2.5 overflow-y-auto p-4">
            {{-- Letterhead --}}
            <div class="flex items-start justify-between gap-3 pb-2" style="border-bottom: 3px solid {{ $brandPrimary }};">
                <div class="flex min-w-0 items-start gap-2.5">
                    @if ($school->logoUrl())
                        <img src="{{ $school->logoUrl() }}" class="h-[90px] w-[90px] shrink-0 object-contain">
                    @endif
                    <div class="min-w-0">
                        <p class="truncate text-lg font-extrabold uppercase leading-tight" style="color: {{ $brandSecondary }};">{{ $school->name }}</p>
                        @if ($motto)
                            <p class="truncate text-[10px] font-bold italic" style="color: {{ $brandPrimary }};">{{ $motto }}</p>
                        @endif
                        @if ($school->website?->contact_address)
                            <p class="mt-1.5 truncate text-[9px] text-gray-600">
                                <svg class="mr-1.5 inline-block h-[8px] w-[8px] align-[-0.5px]" viewBox="0 0 384 512" fill="currentColor"><path d="M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z"/></svg>{{ $school->website->contact_address }}
                            </p>
                        @endif
                        @if ($school->website?->contact_phone)
                            <p class="mt-1 truncate text-[9px] text-gray-600">
                                <svg class="mr-1.5 inline-block h-[8px] w-[8px] align-[-0.5px]" viewBox="0 0 512 512" fill="currentColor"><path d="M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 300.3 211.7 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L184.7 167c13.7-11.1 18.4-30 11.6-46.3l-40-96z"/></svg>{{ $school->website->contact_phone }}
                            </p>
                        @endif
                        @if ($school->website?->contact_email)
                            <p class="mt-1 truncate text-[9px] text-gray-600">
                                <svg class="mr-1.5 inline-block h-[8px] w-[8px] align-[-0.5px]" viewBox="0 0 512 512" fill="currentColor"><path d="M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4l217.6 163.2c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z"/></svg>{{ $school->website->contact_email }}
                            </p>
                        @endif
                        @if ($websiteHost)
                            <p class="mt-1 truncate text-[9px] text-gray-600">
                                <svg class="mr-1.5 inline-block h-[8px] w-[8px] align-[-0.5px]" viewBox="0 0 20 20"><circle cx="10" cy="10" r="9" fill="currentColor"/><ellipse cx="10" cy="10" rx="3.2" ry="9" fill="white"/><rect x="1" y="8.5" width="18" height="3" fill="white"/></svg>{{ $websiteHost }}
                            </p>
                        @endif
                    </div>
                </div>
                <div class="w-[170px] shrink-0 overflow-hidden rounded-[5px] border border-gray-400">
                    <div class="px-1.5 py-1 text-center text-[9px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">Academic Report Card</div>
                    <div class="grid grid-cols-2 divide-x divide-gray-400 border-t border-gray-400 text-[8px] text-gray-600">
                        <div class="px-1.5 py-1">Academic Session:</div>
                        <div class="px-1.5 py-1 text-right font-bold text-gray-900">{{ $examination->session }}</div>
                    </div>
                    <div class="grid grid-cols-2 divide-x divide-gray-400 border-t border-gray-400 text-[8px] text-gray-600">
                        <div class="px-1.5 py-1">Term:</div>
                        <div class="px-1.5 py-1 text-right font-bold" style="color: {{ $brandSecondary }};">{{ $examination->term->label() }}</div>
                    </div>
                </div>
            </div>

            {{-- Student info --}}
            <div class="flex gap-3 overflow-hidden rounded-[5px] border border-gray-400 p-2.5">
                <div class="shrink-0">
                    @if ($student->photoUrl())
                        <img src="{{ $student->photoUrl() }}" class="h-[125px] w-[100px] border border-gray-300 object-cover">
                    @else
                        <span class="flex h-[125px] w-[100px] items-center justify-center border border-gray-300 bg-gray-50 text-[8px] text-gray-400">No Photo</span>
                    @endif
                </div>
                <div class="grid flex-1 grid-cols-[auto_1fr_auto_1fr] gap-x-3 gap-y-1 text-[9.5px]">
                    <span class="text-[8px] font-bold uppercase text-gray-500">Student Name:</span> <span class="font-bold text-gray-900">{{ $student->fullName() }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Next Term Begins:</span> <span class="font-bold text-gray-900">{{ $nextTermBegins?->format('M j, Y') ?? '—' }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Admission Number:</span> <span class="font-bold text-gray-900">{{ $student->admission_number }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Number in Class:</span> <span class="font-bold text-gray-900">{{ $numberInClass }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Class:</span> <span class="font-bold text-gray-900">{{ $examination->class_name }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Average Score:</span> <span class="font-bold text-gray-900">{{ $summary['average'] !== null ? $summary['average'].'%' : '—' }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Date of Birth:</span> <span class="font-bold text-gray-900">{{ $student->date_of_birth?->format('jS F, Y') ?? '—' }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Overall Grade:</span>
                    <span class="flex items-center gap-1">
                        <span class="px-1.5 py-0.5 text-[9px] font-bold text-white" style="background-color: {{ $overallColor }};">{{ $overallGrade }}</span>
                        @if ($overallDescription)<span class="text-[9px] text-gray-600">({{ $overallDescription }})</span>@endif
                    </span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Gender:</span> <span class="font-bold text-gray-900">{{ $student->gender?->label() ?? '—' }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Position in Class:</span> <span class="font-bold text-gray-900">{{ $positionLabel }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">House:</span> <span class="font-bold text-gray-900">{{ $student->house ?? '—' }}</span>
                    <span class="text-[8px] font-bold uppercase text-gray-500">Attendance (%):</span> <span class="font-bold text-gray-900">{{ $attendance && $attendance['percent'] !== null ? $attendance['percent'].'%' : '—' }}</span>
                </div>
            </div>

            {{-- Academic performance --}}
            <div class="overflow-hidden rounded-[5px] border border-gray-400">
                <div class="py-1 text-center text-[9.5px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">Academic Performance</div>
                <table class="w-full border-collapse text-left text-[9px]">
                    <thead>
                        <tr style="background-color: {{ $brandSecondary }}14;">
                            <th class="border border-gray-400 px-1.5 py-1 text-center font-bold uppercase" style="color: {{ $brandSecondary }};">S/N</th>
                            <th class="border border-gray-400 px-1.5 py-1 font-bold uppercase" style="color: {{ $brandSecondary }};">Subject</th>
                            <th class="border border-gray-400 px-1.5 py-1 text-center font-bold uppercase" style="color: {{ $brandSecondary }};">Test<br><span class="text-[7px] font-normal normal-case">({{ $testMaxLabel }} Marks)</span></th>
                            <th class="border border-gray-400 px-1.5 py-1 text-center font-bold uppercase" style="color: {{ $brandSecondary }};">Exam<br><span class="text-[7px] font-normal normal-case">({{ $examMaxLabel }} Marks)</span></th>
                            <th class="border border-gray-400 px-1.5 py-1 text-center font-bold uppercase" style="color: {{ $brandSecondary }};">Total<br><span class="text-[7px] font-normal normal-case">({{ $subjectMaxLabel }} Marks)</span></th>
                            <th class="border border-gray-400 px-1.5 py-1 text-center font-bold uppercase" style="color: {{ $brandSecondary }};">Percentage<br><span class="text-[7px] font-normal normal-case">(%)</span></th>
                            <th class="border border-gray-400 px-1.5 py-1 text-center font-bold uppercase" style="color: {{ $brandSecondary }};">Grade</th>
                            <th class="border border-gray-400 px-1.5 py-1 text-center font-bold uppercase" style="color: {{ $brandSecondary }};">Remark</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($subjects as $subject)
                            @php $score = $subject->scores->first(); $percentage = $score?->percentage(); @endphp
                            <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                                <td class="border border-gray-300 px-1.5 py-1 text-center text-gray-500">{{ $loop->iteration }}</td>
                                <td class="border border-gray-300 px-1.5 py-1 font-bold text-gray-900">{{ $subject->name }}</td>
                                <td class="border border-gray-300 px-1.5 py-1 text-center text-gray-700">{{ $score?->test_score !== null ? rtrim(rtrim($score->test_score, '0'), '.') : '—' }}</td>
                                <td class="border border-gray-300 px-1.5 py-1 text-center text-gray-700">{{ $score?->exam_score !== null ? rtrim(rtrim($score->exam_score, '0'), '.') : '—' }}</td>
                                <td class="border border-gray-300 px-1.5 py-1 text-center font-bold text-gray-900">{{ $score ? rtrim(rtrim($score->score, '0'), '.') : '—' }}</td>
                                <td class="border border-gray-300 px-1.5 py-1 text-center text-gray-700">{{ $percentage !== null ? $percentage.'%' : '—' }}</td>
                                <td class="border border-gray-300 px-1.5 py-1 text-center font-bold" style="color: {{ $colorForPercentage($percentage) }};">{{ $score ? $score->grade() : '—' }}</td>
                                <td class="border border-gray-300 px-1.5 py-1 text-center text-gray-700">{{ $percentage !== null ? \App\Models\GradeBand::describe($school, $percentage) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 font-bold text-gray-900">
                            <td colspan="2" class="border border-gray-400 px-1.5 py-1">Total</td>
                            <td class="border border-gray-400 px-1.5 py-1 text-center">{{ rtrim(rtrim((string) $totalTest, '0'), '.') }}</td>
                            <td class="border border-gray-400 px-1.5 py-1 text-center">{{ rtrim(rtrim((string) $totalExam, '0'), '.') }}</td>
                            <td class="border border-gray-400 px-1.5 py-1 text-center">{{ rtrim(rtrim((string) $totalScore, '0'), '.') }}</td>
                            <td class="border border-gray-400 px-1.5 py-1 text-center">{{ $summary['average'] !== null ? $summary['average'].'%' : '—' }}</td>
                            <td class="border border-gray-400 px-1.5 py-1 text-center" style="color: {{ $overallColor }};">{{ $overallGrade }}</td>
                            <td class="border border-gray-400 px-1.5 py-1"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Summary / Grade key / Attendance --}}
            <div class="grid grid-cols-3 gap-2">
                <div class="overflow-hidden rounded-[5px] border border-gray-400">
                    <div class="py-1 text-center text-[8.5px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">Summary of Performance</div>
                    <table class="w-full border-collapse text-[8px]">
                        <tbody>
                            <tr style="background-color: #fdf8ee;"><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase" style="color: {{ $brandSecondary }};">Total Marks Obtained:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ rtrim(rtrim((string) $totalScore, '0'), '.') }}/{{ $totalMax }}</td></tr>
                            <tr class="bg-white"><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase" style="color: {{ $brandSecondary }};">Average Score:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ $summary['average'] !== null ? $summary['average'].'%' : '—' }}</td></tr>
                            <tr style="background-color: #fdf8ee;"><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase" style="color: {{ $brandSecondary }};">Overall Grade:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold" style="color: {{ $overallColor }};">{{ $overallGrade }}{{ $overallDescription ? ' ('.strtoupper($overallDescription).')' : '' }}</td></tr>
                            <tr class="bg-white"><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase" style="color: {{ $brandSecondary }};">Position in Class:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ $positionLabel }}</td></tr>
                            <tr style="background-color: #fdf8ee;"><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase" style="color: {{ $brandSecondary }};">Number in Class:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ $numberInClass }}</td></tr>
                            <tr class="bg-white"><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase" style="color: {{ $brandSecondary }};">Attendance Percentage:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ $attendance && $attendance['percent'] !== null ? $attendance['percent'].'%' : '—' }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="overflow-hidden rounded-[5px] border border-gray-400">
                    <div class="py-1 text-center text-[8.5px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">Grade Key (By Percentage)</div>
                    <table class="w-full border-collapse text-[7px]">
                        <thead>
                            <tr class="bg-gray-100 text-gray-500">
                                <th class="whitespace-nowrap border border-gray-400 px-1 py-1 text-left font-bold uppercase">Grade</th>
                                <th class="whitespace-nowrap border border-gray-400 px-1 py-1 text-left font-bold uppercase">Percentage Range</th>
                                <th class="whitespace-nowrap border border-gray-400 px-1 py-1 text-left font-bold uppercase">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($gradeKey as $band)
                                <tr class="{{ $loop->even ? 'bg-gray-50' : 'bg-white' }}">
                                    <td class="whitespace-nowrap border border-gray-400 px-1 py-1 font-bold" style="color: {{ $gradeColors[$loop->index % count($gradeColors)] }};">{{ $band['letter'] }}</td>
                                    <td class="whitespace-nowrap border border-gray-400 px-1 py-1">{{ $band['min_percent'] }}-{{ $band['max_percent'] }}</td>
                                    <td class="whitespace-nowrap border border-gray-400 px-1 py-1">{{ $band['description'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="overflow-hidden rounded-[5px] border border-gray-400">
                    <div class="py-1 text-center text-[8.5px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">Attendance Record</div>
                    @if ($attendance)
                        <table class="w-full border-collapse text-[8px]">
                            <tbody>
                                <tr><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase text-gray-700">Total School Days:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ $attendance['total'] }}</td></tr>
                                <tr><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase text-gray-700">Days Present:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ $attendance['present'] }}</td></tr>
                                <tr><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase text-gray-700">Days Absent:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ $attendance['absent'] }}</td></tr>
                                <tr><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase text-gray-700">Days Late:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ $attendance['late'] }}</td></tr>
                                <tr><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase text-gray-700">Excused Absences:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold text-gray-900">{{ $attendance['excused'] }}</td></tr>
                                <tr style="background-color: #fdf8ee;"><td class="border border-gray-400 px-1.5 py-1 font-bold uppercase" style="color: {{ $brandSecondary }};">Attendance Percentage:</td><td class="border border-gray-400 px-1.5 py-1 text-right font-bold" style="color: {{ $brandSecondary }};">{{ $attendance['percent'] !== null ? $attendance['percent'].'%' : '—' }}</td></tr>
                            </tbody>
                        </table>
                    @else
                        {{-- Not "no attendance was taken": the register may be
                             full. Attendance is counted between a term's start
                             and end dates, and without them there is no window
                             to count it within - so the card names the fix
                             rather than leaving a blank that reads as an empty
                             register. --}}
                        <div class="p-1.5 text-[8.5px] leading-[1.4] text-gray-400">
                            Term dates not set
                            <span class="block">Add this term's start and end dates under Academics &rarr; Terms to show attendance here.</span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Remarks --}}
            <div class="overflow-hidden rounded-[5px] border border-gray-400">
                <div class="py-1 text-center text-[9.5px] font-bold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">Remarks</div>
                <table class="w-full border-collapse text-[9px]">
                    <tbody>
                        <tr>
                            <td class="w-32 border border-gray-400 p-1.5 align-top font-bold" style="color: {{ $brandSecondary }};">Class Teacher's Remark:</td>
                            <td class="border border-gray-400 p-1.5 italic text-gray-700">{{ $report->teacher_remark ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td class="w-32 border border-gray-400 p-1.5 align-top font-bold" style="color: {{ $brandSecondary }};">Principal's Remark:</td>
                            <td class="border border-gray-400 p-1.5 italic text-gray-700">{{ $report->principal_remark ?: '—' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Signatures --}}
        <div class="shrink-0 p-4 pt-1">
            <div class="grid grid-cols-4 gap-4 text-[8.5px]">
                <div class="text-center">
                    <div class="border-b border-dashed border-gray-400 pb-4"></div>
                    <p class="mt-1 text-[7.5px] font-bold uppercase tracking-wide" style="color: {{ $brandSecondary }};">Class Teacher</p>
                    <p class="text-gray-700">{{ $classTeacher?->staff?->fullName() ?: '—' }}</p>
                    <p class="text-[7px] text-gray-400">Date: {{ now()->format('jS F, Y') }}</p>
                </div>
                <div class="text-center">
                    @if ($school->principalSignatureUrl())
                        <img src="{{ $school->principalSignatureUrl() }}" class="mx-auto h-7 object-contain">
                    @else
                        <div class="border-b border-dashed border-gray-400 pb-4"></div>
                    @endif
                    <p class="mt-1 text-[7.5px] font-bold uppercase tracking-wide" style="color: {{ $brandSecondary }};">Principal</p>
                    <p class="text-gray-700">{{ $school->principal_name ?: '—' }}</p>
                    <p class="text-[7px] text-gray-400">Date: {{ now()->format('jS F, Y') }}</p>
                </div>
                <div class="flex items-start justify-center">
                    <div class="flex h-14 w-14 flex-col items-center justify-center rounded-full text-center" style="border: 2.5px double {{ $brandSecondary }};">
                        @if ($school->logoUrl())
                            <img src="{{ $school->logoUrl() }}" class="h-6 w-6 object-contain">
                        @endif
                        <span class="mt-0.5 text-[5px] font-bold uppercase tracking-wide" style="color: {{ $brandSecondary }};">Approved</span>
                    </div>
                </div>
                <div class="text-center">
                    <div class="border-b border-dashed border-gray-400 pb-4"></div>
                    <p class="mt-1 text-[7.5px] font-bold uppercase tracking-wide" style="color: {{ $brandSecondary }};">Parent / Guardian</p>
                    <p class="text-gray-700">{{ $student->guardian_name ?: '—' }}</p>
                    <p class="text-[7px] text-gray-400">Date: ________________</p>
                </div>
            </div>
        </div>

        {{-- Bottom bar --}}
        <div class="shrink-0 py-1.5 text-center text-[8.5px] font-bold uppercase tracking-widest text-white" style="background-color: {{ $brandSecondary }};">
            {{ $motto ?: ($school->website?->slogan ?? $school->name) }}
        </div>
    </div>
</div>
