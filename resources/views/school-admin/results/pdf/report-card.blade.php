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

    // dompdf's `enable_remote` option is off, so it can only read images via
    // local filesystem path, never the http(s) URLs used everywhere else.
    $logoPath = $school->logoAbsolutePath();
    $photoPath = $student->photoAbsolutePath();
    $signaturePath = $school->principalSignatureAbsolutePath();

    // dompdf does not render raw inline <svg> tags mixed into the HTML flow,
    // but it does render an <img> whose src is an SVG data URI - so contact
    // icons are built as data URIs here rather than inlined as <svg> markup.
    $svgIcon = fn (string $path, string $viewBox = '0 0 512 512') => 'data:image/svg+xml;base64,'.base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.$viewBox.'"><path fill="#4b5563" d="'.$path.'"/></svg>'
    );
    $iconAddress = $svgIcon('M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z', '0 0 384 512');
    $iconPhone = $svgIcon('M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 300.3 211.7 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L184.7 167c13.7-11.1 18.4-30 11.6-46.3l-40-96z');
    $iconEmail = $svgIcon('M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4l217.6 163.2c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z');

    // Built from plain circle/ellipse/rect primitives (rather than a
    // hand-recalled Font Awesome globe path) so the exact shape is under our
    // control and known-good, instead of risking a garbled complex path.
    $iconWebsite = 'data:image/svg+xml;base64,'.base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="9" fill="#4b5563"/><ellipse cx="10" cy="10" rx="3.2" ry="9" fill="white"/><rect x="1" y="8.5" width="18" height="3" fill="white"/></svg>'
    );
@endphp
<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <style>
            @page { size: A4; margin: 10mm; }
            body { margin: 0; padding: 0; font-family: sans-serif; color: #111827; font-size: 9.5px; }
            table { border-collapse: collapse; width: 100%; }
            .plain td { border: none; }
            .box { border: 1.5px solid #9ca3af; border-radius: 5px; overflow: hidden; }
            .bar { background-color: {{ $brandSecondary }}; color: #ffffff; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; padding: 5px 8px; font-size: 9.5px; }
            .label { font-size: 7.5px; text-transform: uppercase; color: #6b7280; font-weight: bold; }
            .value { font-size: 9.5px; font-weight: bold; color: #111827; }
            .grid td { border: none; padding: 2.5px 4px; }
            .data-table td, .data-table th { border: 1px solid #9ca3af; padding: 4px 5px; }
            .data-table th { text-transform: uppercase; }
            .kv-table td { border: 1px solid #9ca3af; padding: 2.5px 4px; }
        </style>
    </head>
    <body>
        <table style="border: 2.5px solid {{ $brandSecondary }}; border-radius: 5px; overflow: hidden;">
            <tr>
                <td style="padding: 10px;">

                    {{-- Letterhead --}}
                    <table class="plain" style="border-bottom: 3px solid {{ $brandPrimary }}; padding-bottom: 8px; margin-bottom: 10px;">
                        <tr>
                            @if ($logoPath)
                                <td style="width: 96px; vertical-align: top;">
                                    <img src="{{ $logoPath }}" style="width: 90px; height: 90px; object-fit: contain;">
                                </td>
                            @endif
                            <td style="vertical-align: top;">
                                <div style="font-size: 20px; font-weight: bold; text-transform: uppercase; color: {{ $brandSecondary }};">{{ $school->name }}</div>
                                @if ($motto)
                                    <div style="font-size: 9.5px; font-weight: bold; font-style: italic; color: {{ $brandPrimary }};">{{ $motto }}</div>
                                @endif
                                @if ($school->website?->contact_address)
                                    <div style="font-size: 8.5px; color: #4b5563; margin-top: 5px;">
                                        <img src="{{ $iconAddress }}" style="width: 8px; height: 8px; vertical-align: middle; margin-right: 6px;">{{ $school->website->contact_address }}
                                    </div>
                                @endif
                                @if ($school->website?->contact_phone)
                                    <div style="font-size: 8.5px; color: #4b5563; margin-top: 3px;">
                                        <img src="{{ $iconPhone }}" style="width: 8px; height: 8px; vertical-align: middle; margin-right: 6px;">{{ $school->website->contact_phone }}
                                    </div>
                                @endif
                                @if ($school->website?->contact_email)
                                    <div style="font-size: 8.5px; color: #4b5563; margin-top: 3px;">
                                        <img src="{{ $iconEmail }}" style="width: 8px; height: 8px; vertical-align: middle; margin-right: 6px;">{{ $school->website->contact_email }}
                                    </div>
                                @endif
                                @if ($websiteHost)
                                    <div style="font-size: 8.5px; color: #4b5563; margin-top: 3px;">
                                        <img src="{{ $iconWebsite }}" style="width: 8px; height: 8px; vertical-align: middle; margin-right: 6px;">{{ $websiteHost }}
                                    </div>
                                @endif
                            </td>
                            <td style="width: 170px; vertical-align: top;">
                                <div class="box">
                                    <table>
                                        <tr>
                                            <td colspan="2" style="border: none; background-color: {{ $brandSecondary }}; color: #ffffff; font-size: 9px; font-weight: bold; text-transform: uppercase; text-align: center; padding: 5px 6px;">Academic Report Card</td>
                                        </tr>
                                        <tr>
                                            <td style="border: none; border-right: 1px solid #9ca3af; font-size: 8px; color: #4b5563; padding: 4px 6px;">Academic Session:</td>
                                            <td style="border: none; font-size: 8px; font-weight: bold; color: #111827; padding: 4px 6px; text-align: right;">{{ $examination->session }}</td>
                                        </tr>
                                        <tr>
                                            <td style="border: none; border-right: 1px solid #9ca3af; border-top: 1px solid #9ca3af; font-size: 8px; color: #4b5563; padding: 4px 6px;">Term:</td>
                                            <td style="border: none; border-top: 1px solid #9ca3af; font-size: 8px; font-weight: bold; color: {{ $brandSecondary }}; padding: 4px 6px; text-align: right;">{{ $examination->term->label() }}</td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    </table>

                    {{-- Student info --}}
                    <div class="box" style="margin-bottom: 10px;">
                    <table>
                        <tr>
                            <td style="width: 112px; vertical-align: top; padding: 6px; border: none;">
                                @if ($photoPath)
                                    <img src="{{ $photoPath }}" style="width: 100px; height: 125px; object-fit: cover; border: 1px solid #d1d5db;">
                                @else
                                    <div style="width: 100px; height: 125px; background-color: #f3f4f6; border: 1px solid #d1d5db; text-align: center; line-height: 125px; font-size: 8px; color: #9ca3af;">No Photo</div>
                                @endif
                            </td>
                            <td style="vertical-align: top; padding: 6px; border: none;">
                                <table class="grid">
                                    <tr>
                                        <td class="label" style="width: 96px;">Student Name:</td>
                                        <td class="value" style="width: 140px;">{{ $student->fullName() }}</td>
                                        <td class="label" style="width: 100px;">Next Term Begins:</td>
                                        <td class="value">{{ $nextTermBegins?->format('M j, Y') ?? '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Admission Number:</td>
                                        <td class="value">{{ $student->admission_number }}</td>
                                        <td class="label">Number in Class:</td>
                                        <td class="value">{{ $numberInClass }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Class:</td>
                                        <td class="value">{{ $examination->class_name }}</td>
                                        <td class="label">Average Score:</td>
                                        <td class="value">{{ $summary['average'] !== null ? $summary['average'].'%' : '—' }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Date of Birth:</td>
                                        <td class="value">{{ $student->date_of_birth?->format('jS F, Y') ?? '—' }}</td>
                                        <td class="label">Overall Grade:</td>
                                        <td class="value"><span style="background-color: {{ $overallColor }}; color: #ffffff; font-weight: bold; padding: 1px 6px; font-size: 9px;">{{ $overallGrade }}</span> @if ($overallDescription) <span style="font-size: 8.5px; color: #4b5563;">({{ $overallDescription }})</span> @endif</td>
                                    </tr>
                                    <tr>
                                        <td class="label">Gender:</td>
                                        <td class="value">{{ $student->gender?->label() ?? '—' }}</td>
                                        <td class="label">Position in Class:</td>
                                        <td class="value">{{ $positionLabel }}</td>
                                    </tr>
                                    <tr>
                                        <td class="label">House:</td>
                                        <td class="value">{{ $student->house ?? '—' }}</td>
                                        <td class="label">Attendance (%):</td>
                                        <td class="value">{{ $attendance && $attendance['percent'] !== null ? $attendance['percent'].'%' : '—' }}</td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                    </div>

                    {{-- Academic performance --}}
                    <div class="box" style="margin-bottom: 10px;">
                    <div class="bar" style="text-align: center;">Academic Performance</div>
                    <table class="data-table" style="border: none;">
                        <thead>
                            <tr style="background-color: {{ $brandSecondary }}0f;">
                                <th style="text-align: center; font-weight: bold; color: {{ $brandSecondary }}; width: 22px;">S/N</th>
                                <th style="font-weight: bold; color: {{ $brandSecondary }};">Subject</th>
                                <th style="text-align: center; font-weight: bold; color: {{ $brandSecondary }};">Test<br><span style="font-weight: normal; font-size: 7px; text-transform: none;">({{ $testMaxLabel }} Marks)</span></th>
                                <th style="text-align: center; font-weight: bold; color: {{ $brandSecondary }};">Exam<br><span style="font-weight: normal; font-size: 7px; text-transform: none;">({{ $examMaxLabel }} Marks)</span></th>
                                <th style="text-align: center; font-weight: bold; color: {{ $brandSecondary }};">Total<br><span style="font-weight: normal; font-size: 7px; text-transform: none;">({{ $subjectMaxLabel }} Marks)</span></th>
                                <th style="text-align: center; font-weight: bold; color: {{ $brandSecondary }};">Percentage<br><span style="font-weight: normal; font-size: 7px; text-transform: none;">(%)</span></th>
                                <th style="text-align: center; font-weight: bold; color: {{ $brandSecondary }}; width: 30px;">Grade</th>
                                <th style="text-align: center; font-weight: bold; color: {{ $brandSecondary }};">Remark</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($subjects as $subject)
                                @php $score = $subject->scores->first(); $percentage = $score?->percentage(); @endphp
                                <tr style="background-color: {{ $loop->even ? '#f9fafb' : '#ffffff' }};">
                                    <td style="text-align: center; color: #6b7280;">{{ $loop->iteration }}</td>
                                    <td style="font-weight: bold;">{{ $subject->name }}</td>
                                    <td style="text-align: center;">{{ $score?->test_score !== null ? rtrim(rtrim($score->test_score, '0'), '.') : '—' }}</td>
                                    <td style="text-align: center;">{{ $score?->exam_score !== null ? rtrim(rtrim($score->exam_score, '0'), '.') : '—' }}</td>
                                    <td style="text-align: center; font-weight: bold;">{{ $score ? rtrim(rtrim($score->score, '0'), '.') : '—' }}</td>
                                    <td style="text-align: center;">{{ $percentage !== null ? $percentage.'%' : '—' }}</td>
                                    <td style="text-align: center; font-weight: bold; color: {{ $colorForPercentage($percentage) }};">{{ $score ? $score->grade() : '—' }}</td>
                                    <td style="text-align: center;">{{ $percentage !== null ? \App\Models\GradeBand::describe($school, $percentage) : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr style="background-color: #f3f4f6; font-weight: bold;">
                                <td colspan="2">Total</td>
                                <td style="text-align: center;">{{ rtrim(rtrim((string) $totalTest, '0'), '.') }}</td>
                                <td style="text-align: center;">{{ rtrim(rtrim((string) $totalExam, '0'), '.') }}</td>
                                <td style="text-align: center;">{{ rtrim(rtrim((string) $totalScore, '0'), '.') }}</td>
                                <td style="text-align: center;">{{ $summary['average'] !== null ? $summary['average'].'%' : '—' }}</td>
                                <td style="text-align: center; color: {{ $overallColor }};">{{ $overallGrade }}</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    </div>

                    {{-- Summary / Grade key / Attendance --}}
                    <table class="plain" style="margin-bottom: 10px;">
                        <tr>
                            <td style="width: 33.3%; vertical-align: top; padding-right: 3px;">
                                <div class="box">
                                <div class="bar" style="text-align: center;">Summary of Performance</div>
                                <table class="kv-table" style="border-top: none;">
                                    <tr style="background-color: #fdf8ee;"><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; color: {{ $brandSecondary }}; font-weight: bold;">Total Marks Obtained:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ rtrim(rtrim((string) $totalScore, '0'), '.') }}/{{ $totalMax }}</td></tr>
                                    <tr style="background-color: #ffffff;"><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; color: {{ $brandSecondary }}; font-weight: bold;">Average Score:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ $summary['average'] !== null ? $summary['average'].'%' : '—' }}</td></tr>
                                    <tr style="background-color: #fdf8ee;"><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; color: {{ $brandSecondary }}; font-weight: bold;">Overall Grade:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right; color: {{ $overallColor }};">{{ $overallGrade }}{{ $overallDescription ? ' ('.strtoupper($overallDescription).')' : '' }}</td></tr>
                                    <tr style="background-color: #ffffff;"><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; color: {{ $brandSecondary }}; font-weight: bold;">Position in Class:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ $positionLabel }}</td></tr>
                                    <tr style="background-color: #fdf8ee;"><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; color: {{ $brandSecondary }}; font-weight: bold;">Number in Class:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ $numberInClass }}</td></tr>
                                    <tr style="background-color: #ffffff;"><td style="border-left: none; border-bottom: none; font-size: 7.5px; text-transform: uppercase; color: {{ $brandSecondary }}; font-weight: bold;">Attendance Percentage:</td><td style="border-right: none; border-bottom: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ $attendance && $attendance['percent'] !== null ? $attendance['percent'].'%' : '—' }}</td></tr>
                                </table>
                                </div>
                            </td>
                            <td style="width: 33.3%; vertical-align: top; padding: 0 3px;">
                                <div class="box">
                                <div class="bar" style="text-align: center;">Grade Key (By Percentage)</div>
                                <table class="kv-table" style="border-top: none;">
                                    <tr style="background-color: #f3f4f6;">
                                        <td style="border-left: none; font-size: 6.5px; text-transform: uppercase; font-weight: bold; color: #6b7280;">Grade</td>
                                        <td style="font-size: 6.5px; text-transform: uppercase; font-weight: bold; color: #6b7280; white-space: nowrap;">Percentage Range</td>
                                        <td style="border-right: none; font-size: 6.5px; text-transform: uppercase; font-weight: bold; color: #6b7280;">Remark</td>
                                    </tr>
                                    @foreach ($gradeKey as $band)
                                        <tr style="background-color: {{ $loop->even ? '#f9fafb' : '#ffffff' }};">
                                            <td style="border-left: none; {{ $loop->last ? 'border-bottom: none;' : '' }} font-size: 8px; font-weight: bold; color: {{ $gradeColors[$loop->index % count($gradeColors)] }};">{{ $band['letter'] }}</td>
                                            <td style="{{ $loop->last ? 'border-bottom: none;' : '' }} font-size: 8px; white-space: nowrap;">{{ $band['min_percent'] }}-{{ $band['max_percent'] }}</td>
                                            <td style="border-right: none; {{ $loop->last ? 'border-bottom: none;' : '' }} font-size: 8px; white-space: nowrap;">{{ $band['description'] }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                                </div>
                            </td>
                            <td style="width: 33.3%; vertical-align: top; padding-left: 3px;">
                                <div class="box">
                                <div class="bar" style="text-align: center;">Attendance Record</div>
                                @if ($attendance)
                                    <table class="kv-table" style="border-top: none;">
                                        <tr><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; font-weight: bold; color: #374151;">Total School Days:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ $attendance['total'] }}</td></tr>
                                        <tr><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; font-weight: bold; color: #374151;">Days Present:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ $attendance['present'] }}</td></tr>
                                        <tr><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; font-weight: bold; color: #374151;">Days Absent:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ $attendance['absent'] }}</td></tr>
                                        <tr><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; font-weight: bold; color: #374151;">Days Late:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ $attendance['late'] }}</td></tr>
                                        <tr><td style="border-left: none; font-size: 7.5px; text-transform: uppercase; font-weight: bold; color: #374151;">Excused Absences:</td><td style="border-right: none; font-size: 8.5px; font-weight: bold; text-align: right;">{{ $attendance['excused'] }}</td></tr>
                                        <tr style="background-color: #fdf8ee;"><td style="border-left: none; border-bottom: none; font-size: 7.5px; text-transform: uppercase; font-weight: bold; color: {{ $brandSecondary }};">Attendance Percentage:</td><td style="border-right: none; border-bottom: none; font-size: 8.5px; font-weight: bold; text-align: right; color: {{ $brandSecondary }};">{{ $attendance['percent'] !== null ? $attendance['percent'].'%' : '—' }}</td></tr>
                                    </table>
                                @else
                                    <table class="kv-table" style="border-top: none;"><tr><td style="border: none; padding: 6px; font-size: 8.5px; color: #9ca3af;">Term dates not set</td></tr></table>
                                @endif
                                </div>
                            </td>
                        </tr>
                    </table>

                    {{-- Remarks --}}
                    <div class="box" style="margin-bottom: 16px;">
                    <div class="bar">Remarks</div>
                    <table>
                        <tr>
                            <td style="width: 130px; border: none; border-right: 1px solid #9ca3af; padding: 5px 6px; font-size: 8.5px; font-weight: bold; color: {{ $brandSecondary }};">Class Teacher's Remark:</td>
                            <td style="border: none; padding: 5px 6px; font-size: 9px; font-style: italic;">{{ $report->teacher_remark ?: '—' }}</td>
                        </tr>
                        <tr>
                            <td style="border: none; border-right: 1px solid #9ca3af; border-top: 1px solid #9ca3af; padding: 5px 6px; font-size: 8.5px; font-weight: bold; color: {{ $brandSecondary }};">Principal's Remark:</td>
                            <td style="border: none; border-top: 1px solid #9ca3af; padding: 5px 6px; font-size: 9px; font-style: italic;">{{ $report->principal_remark ?: '—' }}</td>
                        </tr>
                    </table>
                    </div>

                    {{-- Signatures --}}
                    <table class="plain">
                        <tr>
                            <td style="width: 25%; text-align: center; vertical-align: top;">
                                <div style="border-bottom: 1px dashed #9ca3af; height: 26px;"></div>
                                <div style="font-size: 7.5px; color: {{ $brandSecondary }}; margin-top: 4px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">Class Teacher</div>
                                <div style="font-size: 8px; color: #374151; margin-top: 1px;">{{ $classTeacher?->staff?->fullName() ?: '—' }}</div>
                                <div style="font-size: 7px; color: #9ca3af; margin-top: 1px;">Date: {{ now()->format('jS F, Y') }}</div>
                            </td>
                            <td style="width: 25%; text-align: center; vertical-align: top;">
                                @if ($signaturePath)
                                    <img src="{{ $signaturePath }}" style="height: 26px;">
                                @else
                                    <div style="border-bottom: 1px dashed #9ca3af; height: 26px;"></div>
                                @endif
                                <div style="font-size: 7.5px; color: {{ $brandSecondary }}; margin-top: 4px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">Principal</div>
                                <div style="font-size: 8px; color: #374151; margin-top: 1px;">{{ $school->principal_name ?: '—' }}</div>
                                <div style="font-size: 7px; color: #9ca3af; margin-top: 1px;">Date: {{ now()->format('jS F, Y') }}</div>
                            </td>
                            <td style="width: 25%; text-align: center; vertical-align: top;">
                                <div style="width: 56px; height: 56px; margin: 0 auto; border: 2.5px double {{ $brandSecondary }}; border-radius: 50%; text-align: center; padding-top: 6px;">
                                    @if ($logoPath)
                                        <img src="{{ $logoPath }}" style="width: 26px; height: 26px; object-fit: contain;">
                                    @endif
                                    <div style="font-size: 5px; font-weight: bold; color: {{ $brandSecondary }}; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">Approved</div>
                                </div>
                            </td>
                            <td style="width: 25%; text-align: center; vertical-align: top;">
                                <div style="border-bottom: 1px dashed #9ca3af; height: 26px;"></div>
                                <div style="font-size: 7.5px; color: {{ $brandSecondary }}; margin-top: 4px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px;">Parent / Guardian</div>
                                <div style="font-size: 8px; color: #374151; margin-top: 1px;">{{ $student->guardian_name ?: '—' }}</div>
                                <div style="font-size: 7px; color: #9ca3af; margin-top: 1px;">Date: ________________</div>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
            <tr>
                <td class="bar" style="text-align: center; letter-spacing: 1px;">
                    {{ $motto ?: ($school->website?->slogan ?? $school->name) }}
                </td>
            </tr>
        </table>
    </body>
</html>
