@php
    $brandPrimary = $school->website?->brand_primary_color ?? '#1d4ed8';
    $brandSecondary = $school->website?->brand_secondary_color ?? '#111a35';
    // See _report-card.blade.php, the tagline and the values are two lines.
    $schoolMotto = \App\Support\SchoolMotto::for($school);
    $motto = $schoolMotto->tagline;

    // The letterhead address, see _report-card.blade.php.
    $letterhead = \App\Support\SchoolContact::for($school);

    // The card's third colour, matching the on-screen template exactly. Both
    // files hold it as the same constant: a printed card that differed from
    // the one a School Admin approved on screen would be the whole problem
    // this redesign exists to avoid.
    $brandAccent = '#c8a34a';

    // The same gold, darkened for text on a WHITE ground.
    //
    // #c8a34a is 2.39:1 on white, unreadable as small italic type,
    // and a report card is a document people photocopy. It stays as it
    // is on the navy footer bar (7.19:1 there) and on the rules, which
    // are shapes rather than words. Only the tagline moves, to 5.06:1.
    $brandAccentOnLight = '#8a6a15';

    // Rows are left unpainted so the watermark reads through them. Solid
    // white would punch the crest out wherever a table sits, which on a full
    // page of marks is nearly all of it.
    $rowPlain = 'transparent';
    $rowStriped = '#f4f5f8';

    $footerMotto = $schoolMotto->values;

    // The watermark: this school's OWN crest, faded and centred behind the
    // page. Baked at that opacity rather than faded with CSS, because dompdf
    // ignores opacity on an image, done the CSS way it looks right on screen
    // and prints at full strength over the marks. See
    // CodeImageGenerator::watermarkDataUri().
    //
    // It is sized to 76% of the FULL sheet, not of the printable area inside
    // the margins: A4 is 210mm wide, which dompdf lays out at 96dpi as 794px,
    // so the mark is 603px across. Measuring it against the sheet rather than
    // the text column keeps it the same size on screen and in print even
    // though the two have different margins.
    $pageWidth = 794;
    $printableHeight = 1047; // 297mm less the 10mm margin top and bottom.
    $logoAbsolutePath = $school->logoAbsolutePath();
    $watermark = $logoAbsolutePath
        ? app(\App\Services\CodeImageGenerator::class)->watermarkDataUri($logoAbsolutePath, (int) round($pageWidth * 0.76), 0.07)
        : null;

    $totalScore = $subjects->sum(fn ($subject) => (float) ($subject->scores->first()?->score ?? 0));
    $totalTest = $subjects->sum(fn ($subject) => (float) ($subject->scores->first()?->test_score ?? 0));
    $totalExam = $subjects->sum(fn ($subject) => (float) ($subject->scores->first()?->exam_score ?? 0));
    $totalMax = $subjects->sum('max_score');
    $testMaxLabel = $subjects->first()?->testMaxScore() ?? 40;
    $examMaxLabel = $subjects->first()?->examMaxScore() ?? 60;
    $subjectMaxLabel = $subjects->first()?->max_score ?? 100;

    $overallGrade = $summary['average'] === null ? 'N/A' : \App\Models\GradeBand::resolve($school, $summary['average']);
    $overallDescription = $summary['average'] === null ? null : \App\Models\GradeBand::describe($school, $summary['average']);

    $position = $summary['position'] ?? null;
    $positionLabel = $position ? $position.match (true) {
        in_array($position % 100, [11, 12, 13]) => 'th',
        $position % 10 === 1 => 'st',
        $position % 10 === 2 => 'nd',
        $position % 10 === 3 => 'rd',
        default => 'th',
    } : 'N/A';

    $gradeKey = ($school->gradeBands->isNotEmpty()
        ? $school->gradeBands->sortBy('position')->map(fn ($band) => ['min_percent' => $band->min_percent, 'max_percent' => $band->max_percent, 'letter' => $band->letter, 'description' => $band->description])
        : collect(\App\Models\GradeBand::defaultBands()))->values();

    // A best-to-worst color scale (green through red) applied by the grade
    // band's rank rather than its letter, since a school's configured bands
    // can use any letters/count, this keeps the "green = best" visual
    // language from the reference design working for any grading scale.
    //
    // Each shade is one step darker than the obvious Tailwind 600, because
    // the 600s do not clear 4.5:1 on white at the size a grade letter is
    // printed: green was 3.3:1 and amber 3.19:1. The letter on a report
    // card is the one character a parent looks for first, and it is 11px,
    // too small to qualify for the large-text allowance. These run 5.02:1
    // to 6.47:1 and keep the same green-through-red order.
    $gradeColors = ['#15803d', '#1d4ed8', '#b45309', '#c2410c', '#b91c1c'];
    $colorForPercentage = function (?float $percentage) use ($gradeKey, $gradeColors) {
        if ($percentage === null) {
            return '#111827';
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
    // The Principal's signature: School Admin is the Principal, so this is
    // their own registered signature, in gold, resolved from THIS school's
    // admin account, see App\Support\PrincipalSignature. Gold is baked into
    // the image because dompdf ignores CSS filters, so a screen preview and a
    // printed card cannot disagree about the colour.
    $principalSignature = \App\Support\PrincipalSignature::for($school);
    $signaturePath = $principalSignature?->absolutePath();
    $stampPath = $school->stampAbsolutePath();

    // The class teacher's own signature, from their staff record, theirs to
    // upload, not the school's to supply on their behalf.
    $teacherSignaturePath = $classTeacher?->staff?->signatureAbsolutePath();

    // dompdf does not render raw inline <svg> tags mixed into the HTML flow,
    // but it does render an <img> whose src is an SVG data URI, so contact
    // icons are built as data URIs here rather than inlined as <svg> markup.
    // Each one a white glyph on a filled disc in the school's colour, the same
    // mark the on-screen letterhead draws. The glyph is scaled and centred
    // into the disc from its own viewBox, because the paths below are not all
    // the same shape, a phone is square, a map pin is tall, and a fixed
    // transform would leave one of them off-centre or clipped.
    $svgIcon = function (string $path, string $viewBox = '0 0 512 512') use ($brandSecondary) {
        [, , $width, $height] = array_map('floatval', explode(' ', $viewBox));
        $scale = 280 / max($width, $height);

        return 'data:image/svg+xml;base64,'.base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">'
            .'<circle cx="256" cy="256" r="256" fill="'.$brandSecondary.'"/>'
            .'<g transform="translate('.round((512 - $width * $scale) / 2, 2).' '.round((512 - $height * $scale) / 2, 2).') scale('.round($scale, 4).')">'
            .'<path fill="#ffffff" d="'.$path.'"/>'
            .'</g></svg>'
        );
    };
    $iconAddress = $svgIcon('M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z', '0 0 384 512');
    $iconPhone = $svgIcon('M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 300.3 211.7 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L184.7 167c13.7-11.1 18.4-30 11.6-46.3l-40-96z');
    $iconEmail = $svgIcon('M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4l217.6 163.2c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z');

    // Built from plain circle/ellipse/rect primitives (rather than a
    // hand-recalled Font Awesome globe path) so the exact shape is under our
    // control and known-good, instead of risking a garbled complex path.
    $iconWebsite = 'data:image/svg+xml;base64,'.base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="10" fill="'.$brandSecondary.'"/><ellipse cx="10" cy="10" rx="3.1" ry="7.4" fill="none" stroke="#ffffff" stroke-width="1.1"/><circle cx="10" cy="10" r="7.4" fill="none" stroke="#ffffff" stroke-width="1.1"/><line x1="2.6" y1="10" x2="17.4" y2="10" stroke="#ffffff" stroke-width="1.1"/></svg>'
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
            .label { font-size: 7.5px; text-transform: uppercase; color: #111827; font-weight: bold; }
            .value { font-size: 9.5px; font-weight: bold; color: #111827; }
            .grid td { border: none; padding: 2.5px 4px; }
            .data-table td, .data-table th { border: 1px solid #9ca3af; padding: 4px 5px; }
            .data-table th { text-transform: uppercase; }
            .kv-table td { border: 1px solid #9ca3af; padding: 2.5px 4px; }
        </style>
    </head>
    <body>
        {{-- Absolutely positioned so it is painted first and everything else
             sits over it. dompdf honours position:absolute on a block, which
             is what makes a watermark possible here at all. --}}
        @if ($watermark)
            <div style="position: absolute; top: {{ max(0, (int) round(($printableHeight - $watermark['height']) / 2)) }}px; left: 50%; margin-left: -{{ (int) round($watermark['width'] / 2) }}px; z-index: 0;">
                <img src="{{ $watermark['uri'] }}" width="{{ $watermark['width'] }}" height="{{ $watermark['height'] }}" style="width: {{ $watermark['width'] }}px; height: {{ $watermark['height'] }}px;" alt="">
            </div>
        @endif

        <table style="position: relative; border: 2.5px solid {{ $brandSecondary }}; border-radius: 5px; overflow: hidden;">
            <tr>
                <td style="padding: 10px;">

                    {{-- SEGMENT 1, the letterhead. Mirrors _report-card.blade.php:
                    crest, school name, tagline in the accent colour, contact
                    lines behind round marks, and the title block on the right.
                    Built from tables because dompdf has no flexbox. --}}
                    <table class="plain" style="margin-bottom: 8px;">
                        <tr>
                            @if ($logoPath)
                                <td style="width: 124px; vertical-align: top;">
                                    <img src="{{ $logoPath }}" style="width: 120px; height: 120px;">
                                </td>
                            @endif
                            <td style="vertical-align: top; padding-left: 8px;">
                                <div style="font-size: 26px; font-weight: bold; text-transform: uppercase; color: {{ $brandSecondary }}; font-family: 'Times New Roman', serif; line-height: 1.05;">{{ $school->name }}</div>
                                @if ($motto)
                                    <div style="font-size: 11px; font-style: italic; font-weight: bold; color: {{ $brandAccentOnLight }}; margin-top: 2px;">{{ $motto }}</div>
                                @endif

                                <table class="plain" style="margin-top: 6px;">
                                    @foreach ([[$iconAddress, $letterhead->address], [$iconPhone, $letterhead->phone], [$iconEmail, $letterhead->email], [$iconWebsite, $websiteHost]] as [$icon, $value])
                                        @continue (blank($value))
                                        <tr>
                                            <td style="width: 17px; padding: 1.5px 0; vertical-align: middle;">
                                                <img src="{{ $icon }}" style="width: 14px; height: 14px;">
                                            </td>
                                            <td style="padding: 1.5px 0 1.5px 5px; font-size: 10px; font-weight: bold; color: #1f2937;">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                            <td style="width: 200px; vertical-align: top;">
                                <table style="border: 1.5px solid {{ $brandSecondary }}; border-radius: 5px;">
                                    <tr>
                                        <td style="background-color: {{ $brandSecondary }}; color: #ffffff; font-size: 11px; font-weight: bold; text-transform: uppercase; text-align: center; padding: 5px 6px; letter-spacing: 0.5px;">Academic Report Card</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 5px 6px; background-color: #ffffff;">
                                            <table class="plain">
                                                <tr>
                                                    <td style="font-size: 9px; color: #111827; font-weight: bold;">Academic Session:</td>
                                                    <td style="font-size: 9px; font-weight: bold; color: {{ $brandSecondary }}; text-align: right;">{{ $examination->session }}</td>
                                                </tr>
                                                <tr>
                                                    <td style="font-size: 9px; color: #111827; font-weight: bold;">Term:</td>
                                                    <td style="font-size: 9px; font-weight: bold; color: {{ $brandSecondary }}; text-align: right;">{{ $examination->term->label() }}</td>
                                                </tr>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    {{-- The rule under the letterhead: navy, finished in gold. --}}
                    <table class="plain" style="margin-bottom: 10px;">
                        <tr>
                            <td style="height: 3px; background-color: {{ $brandSecondary }}; font-size: 0; line-height: 0;">&nbsp;</td>
                            <td style="width: 22%; height: 3px; background-color: {{ $brandAccent }}; font-size: 0; line-height: 0;">&nbsp;</td>
                        </tr>
                    </table>

                    {{-- SEGMENT 2, who the card is about. --}}
                    @php
                        $studentFacts = [
                            ['Student Name', $student->fullName()],
                            ['Admission Number', $student->admission_number],
                            ['Class', $examination->class_name],
                            ['Date of Birth', $student->date_of_birth?->format('jS F, Y')],
                            ['Gender', $student->gender?->label()],
                            ['House', $student->house],
                        ];
                        $resultFacts = [
                            ['Next Term Begins', $nextTermBegins?->format('jS F, Y')],
                            ['Number in Class', $numberInClass],
                            ['Average Score', $summary['average'] !== null ? $summary['average'].'%' : null],
                            ['__grade__', null],
                            ['Position in Class', $positionLabel],
                            ['Attendance (%)', $attendance && $attendance['percent'] !== null ? $attendance['percent'].'%' : null],
                        ];
                    @endphp

                    <table style="border: 1.5px solid {{ $brandSecondary }}33; border-radius: 6px; margin-bottom: 10px;">
                        <tr>
                            <td style="padding: 10px;">
                                <table class="plain">
                                    <tr>
                                        <td style="width: 112px; vertical-align: top;">
                                            @if ($photoPath)
                                                <img src="{{ $photoPath }}" style="width: 104px; height: 128px; border: 1.5px solid {{ $brandSecondary }};">
                                            @else
                                                <div style="width: 104px; height: 128px; border: 1.5px solid {{ $brandSecondary }}55; background-color: #f9fafb; text-align: center; line-height: 128px; font-size: 8px; color: #374151;">No Photo</div>
                                            @endif
                                        </td>
                                        @foreach ([$studentFacts, $resultFacts] as $column)
                                            <td style="vertical-align: top; padding-left: 10px;">
                                                <table class="plain">
                                                    @foreach ($column as [$label, $value])
                                                        <tr>
                                                            @if ($label === '__grade__')
                                                                <td style="width: 104px; padding: 2px 0; font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: {{ $brandSecondary }}; vertical-align: middle;">Overall Grade:</td>
                                                                <td style="padding: 2px 0;">
                                                                    <span style="background-color: {{ $overallColor }}; color: #ffffff; font-size: 10px; font-weight: bold; padding: 2px 7px; border-radius: 3px;">{{ $overallGrade }}@if ($overallDescription) ({{ strtoupper($overallDescription) }})@endif</span>
                                                                </td>
                                                            @else
                                                                <td style="width: 104px; padding: 2px 0; font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: {{ $brandSecondary }}; vertical-align: middle;">{{ $label }}:</td>
                                                                <td style="padding: 2px 0; font-size: 9.5px; font-weight: bold; color: #111827;">{{ filled($value) ? $value : 'N/A' }}</td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </table>
                                            </td>
                                        @endforeach
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>

                    {{-- SEGMENT 3, the marks. Mirrors _report-card.blade.php:
                    navy heading, navy column header row with white text, and
                    the grade in the grade's own colour. --}}
                    <table style="border: 1.5px solid {{ $brandSecondary }}; border-radius: 6px; margin-bottom: 10px;">
                        <tr>
                            <td style="background-color: {{ $brandSecondary }}; color: #ffffff; font-size: 11px; font-weight: bold; text-transform: uppercase; text-align: center; padding: 5px; letter-spacing: 0.5px;">Academic Performance</td>
                        </tr>
                        <tr>
                            <td style="padding: 0;">
                                <table>
                                    <thead>
                                        <tr style="background-color: {{ $brandSecondary }};">
                                            @foreach ([
                                                ['S/N', '26px'],
                                                ['Subject', ''],
                                                ['Test', '62px', $testMaxLabel.' Marks'],
                                                ['Exam', '62px', $examMaxLabel.' Marks'],
                                                ['Total', '62px', $subjectMaxLabel.' Marks'],
                                                ['Percentage', '68px', '%'],
                                                ['Grade', '52px'],
                                                ['Remark', '92px'],
                                            ] as $heading)
                                                <th style="{{ $heading[1] ? 'width: '.$heading[1].';' : '' }} border: 0.5px solid #ffffff40; padding: 5px 4px; font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: #ffffff; text-align: {{ $loop->index === 1 ? 'left' : 'center' }};">
                                                    {{ $heading[0] }}
                                                    @isset($heading[2])
                                                        <div style="font-size: 7px; font-weight: normal; text-transform: none;">({{ $heading[2] }})</div>
                                                    @endisset
                                                </th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($subjects as $subject)
                                            @php
                                                $score = $subject->scores->first();
                                                $percentage = $score?->percentage();
                                            @endphp
                                            <tr style="background-color: {{ $loop->even ? $rowStriped : $rowPlain }};">
                                                <td style="border: 1px solid #e5e7eb; padding: 4px; text-align: center; color: #111827;">{{ $loop->iteration }}</td>
                                                <td style="border: 1px solid #e5e7eb; padding: 4px 6px; font-weight: bold; color: #111827;">{{ $subject->name }}</td>
                                                <td style="border: 1px solid #e5e7eb; padding: 4px; text-align: center; color: #111827;">{{ $score?->test_score !== null ? rtrim(rtrim($score->test_score, '0'), '.') : 'N/A' }}</td>
                                                <td style="border: 1px solid #e5e7eb; padding: 4px; text-align: center; color: #111827;">{{ $score?->exam_score !== null ? rtrim(rtrim($score->exam_score, '0'), '.') : 'N/A' }}</td>
                                                <td style="border: 1px solid #e5e7eb; padding: 4px; text-align: center; font-weight: bold; color: #111827;">{{ $score ? rtrim(rtrim($score->score, '0'), '.') : 'N/A' }}</td>
                                                <td style="border: 1px solid #e5e7eb; padding: 4px; text-align: center; color: #111827;">{{ $percentage !== null ? $percentage.'%' : 'N/A' }}</td>
                                                <td style="border: 1px solid #e5e7eb; padding: 4px; text-align: center; font-size: 11px; font-weight: bold; color: {{ $colorForPercentage($percentage) }};">{{ $score ? $score->grade() : 'N/A' }}</td>
                                                <td style="border: 1px solid #e5e7eb; padding: 4px; text-align: center; color: #111827;">{{ $percentage !== null ? \App\Models\GradeBand::describe($school, $percentage) : 'N/A' }}</td>
                                            </tr>
                                        @endforeach
                                        <tr style="background-color: {{ $brandSecondary }}12;">
                                            <td colspan="2" style="border: 1px solid #d1d5db; padding: 5px 6px; font-weight: bold; text-transform: uppercase; color: {{ $brandSecondary }};">Total</td>
                                            <td style="border: 1px solid #d1d5db; padding: 5px 4px; text-align: center; font-weight: bold; color: {{ $brandSecondary }};">{{ rtrim(rtrim((string) $totalTest, '0'), '.') }}</td>
                                            <td style="border: 1px solid #d1d5db; padding: 5px 4px; text-align: center; font-weight: bold; color: {{ $brandSecondary }};">{{ rtrim(rtrim((string) $totalExam, '0'), '.') }}</td>
                                            <td style="border: 1px solid #d1d5db; padding: 5px 4px; text-align: center; font-weight: bold; color: {{ $brandSecondary }};">{{ rtrim(rtrim((string) $totalScore, '0'), '.') }}/{{ $totalMax }}</td>
                                            <td style="border: 1px solid #d1d5db; padding: 5px 4px; text-align: center; font-weight: bold; color: {{ $brandSecondary }};">{{ $summary['average'] !== null ? $summary['average'].'%' : 'N/A' }}</td>
                                            <td style="border: 1px solid #d1d5db; padding: 5px 4px; text-align: center; font-size: 11px; font-weight: bold; color: {{ $overallColor }};">{{ $overallGrade }}</td>
                                            <td style="border: 1px solid #d1d5db; padding: 5px 4px;"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </td>
                        </tr>
                    </table>

                    {{-- SEGMENT 4, three panels reading the same result three
                    ways: the headline figures, the school's OWN grade bands,
                    and the term's register. --}}
                    @php
                        $summaryRows = [
                            ['Total Marks Obtained', rtrim(rtrim((string) $totalScore, '0'), '.').' / '.$totalMax, false, null],
                            ['Average Score', $summary['average'] !== null ? $summary['average'].'%' : 'N/A', false, null],
                            ['Overall Grade', $overallGrade.($overallDescription ? ' ('.strtoupper($overallDescription).')' : ''), false, $overallColor],
                            ['Position in Class', $positionLabel, false, null],
                            ['Number in Class', (string) $numberInClass, false, null],
                            ['Attendance Percentage', $attendance && $attendance['percent'] !== null ? $attendance['percent'].'%' : 'N/A', true, null],
                        ];

                        $attendanceRows = $attendance ? [
                            ['Total School Days', (string) $attendance['total'], false, null],
                            ['Days Present', (string) $attendance['present'], false, null],
                            ['Days Absent', (string) $attendance['absent'], false, null],
                            ['Days Late', (string) $attendance['late'], false, null],
                            ['Excused Absences', (string) $attendance['excused'], false, null],
                            ['Attendance Percentage', $attendance['percent'] !== null ? $attendance['percent'].'%' : 'N/A', true, null],
                        ] : [];
                    @endphp

                    <table class="plain" style="margin-bottom: 10px;">
                        <tr>
                            @foreach ([['Summary of Performance', $summaryRows], ['__gradekey__', []], ['Attendance Record', $attendanceRows]] as $panelIndex => [$title, $rows])
                                <td style="width: 33.33%; vertical-align: top; padding-{{ $panelIndex === 1 ? 'left: 5px; padding-right' : ($panelIndex === 0 ? 'right' : 'left') }}: 5px;">
                                    <table style="border: 1.5px solid {{ $brandSecondary }}; border-radius: 6px;">
                                        <tr>
                                            <td style="background-color: {{ $brandSecondary }}; color: #ffffff; font-size: 9px; font-weight: bold; text-transform: uppercase; text-align: center; padding: 4px 3px; letter-spacing: 0.4px;">
                                                {{ $title === '__gradekey__' ? 'Grade Key (By Percentage)' : $title }}
                                            </td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 0;">
                                                @if ($title === '__gradekey__')
                                                    <table>
                                                        <tr style="background-color: {{ $brandSecondary }}12;">
                                                            @foreach (['Grade', 'Percentage Range', 'Remark'] as $head)
                                                                <th style="border: 1px solid #e5e7eb; padding: 3px 2px; font-size: 7.5px; font-weight: bold; text-transform: uppercase; text-align: center; color: {{ $brandSecondary }};">{{ $head }}</th>
                                                            @endforeach
                                                        </tr>
                                                        @foreach ($gradeKey as $band)
                                                            <tr style="background-color: {{ $loop->even ? $rowStriped : $rowPlain }};">
                                                                <td style="border: 1px solid #e5e7eb; padding: 3px 2px; text-align: center; font-size: 10px; font-weight: bold; color: {{ $gradeColors[$loop->index % count($gradeColors)] }};">{{ $band['letter'] }}</td>
                                                                <td style="border: 1px solid #e5e7eb; padding: 3px 2px; text-align: center; font-size: 7.5px; color: #111827;">{{ $band['min_percent'] }} &ndash; {{ $band['max_percent'] }}</td>
                                                                <td style="border: 1px solid #e5e7eb; padding: 3px 2px; text-align: center; font-size: 7.5px; color: #111827;">{{ $band['description'] }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                @elseif ($rows === [])
                                                    {{-- Deliberately "term dates not set" and not "no
                                                    attendance was taken": the register may be complete
                                                    and only the term's dates missing. --}}
                                                    <div style="padding: 6px; font-size: 8.5px; line-height: 1.4; color: #374151;">
                                                        Term dates not set
                                                        <span style="display: block;">Add this term's start and end dates under Academics &rarr; Terms to show attendance here.</span>
                                                    </div>
                                                @else
                                                    <table>
                                                        @foreach ($rows as [$label, $value, $highlight, $valueColor])
                                                            <tr style="background-color: {{ $highlight ? '#f6ecd2' : ($loop->even ? $rowStriped : $rowPlain) }};">
                                                                <td style="border: 1px solid #e5e7eb; padding: 3px 4px; font-size: 8px; font-weight: bold; text-transform: uppercase; color: {{ $highlight ? $brandSecondary : '#4b5563' }};">{{ $label }}:</td>
                                                                <td style="border: 1px solid #e5e7eb; padding: 3px 4px; font-size: 8px; font-weight: bold; text-align: right; color: {{ $valueColor ?? ($highlight ? $brandSecondary : '#111827') }};">{{ $value }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </table>
                                                @endif
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            @endforeach
                        </tr>
                    </table>

                    {{-- SEGMENT 5, the two remarks written by people. --}}
                    <table style="border: 1.5px solid {{ $brandSecondary }}; border-radius: 6px; margin-bottom: 10px;">
                        <tr>
                            <td style="background-color: {{ $brandSecondary }}; color: #ffffff; font-size: 11px; font-weight: bold; text-transform: uppercase; text-align: center; padding: 5px; letter-spacing: 0.5px;">Remarks</td>
                        </tr>
                        <tr>
                            <td style="padding: 0;">
                                <table>
                                    @foreach ([["Class Teacher's Remark", $report->teacher_remark], ["Principal's Remark", $report->principal_remark]] as $index => [$label, $remark])
                                        <tr style="background-color: {{ $index === 0 ? $rowPlain : $rowStriped }};">
                                            <td style="width: 150px; border: 1px solid #e5e7eb; padding: 6px; font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: {{ $brandSecondary }}; vertical-align: top;">{{ $label }}:</td>
                                            <td style="border: 1px solid #e5e7eb; padding: 6px; font-size: 9px; font-style: italic; color: #111827;">{{ $remark ?: 'N/A' }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                        </tr>
                    </table>

                    {{-- SEGMENT 6, the hands that sign it, with the school's
                    approval stamp between them and the parent's line left for
                    them to complete.

                    Fixed geometry, exactly as on screen: every cell is the
                    same height and each part inside it is too, so the
                    Principal's line prints in the same place on every card
                    whether or not a signature is registered and however long
                    the name beneath it runs. `vertical-align: top` rather than
                    bottom, so the ruled lines cannot drift apart when one
                    label wraps and another does not. --}}
                    <table class="plain">
                        <tr>
                            @foreach ([
                                ["Class Teacher's Signature", $classTeacher?->staff?->fullName(), now()->format('jS F, Y'), $teacherSignaturePath],
                                ["Principal's Signature", $school->principalName(), now()->format('jS F, Y'), $signaturePath],
                                ['__stamp__', null, null, null],
                                ["Parent / Guardian's Signature", $student->guardian_name, null, null],
                            ] as [$role, $name, $date, $signature])
                                <td style="width: 25%; height: 76px; vertical-align: top; text-align: center; padding: 0 5px;">
                                    @if ($role === '__stamp__')
                                        @if ($stampPath)
                                            {{-- A local path, not a data URI:
                                                 dompdf cannot fetch an address
                                                 and a base64 copy bloats every
                                                 generated card. --}}
                                            <img src="{{ $stampPath }}" style="max-height: 70px; max-width: 100%; margin-top: 3px;">
                                        @else
                                            <div style="width: 62px; height: 62px; border: 2.5px solid {{ $brandSecondary }}; border-radius: 31px; margin: 7px auto 0; text-align: center;">
                                                @if ($logoPath)
                                                    <img src="{{ $logoPath }}" style="width: 22px; height: 22px; margin-top: 12px;">
                                                @endif
                                                <div style="font-size: 5.5px; font-weight: bold; text-transform: uppercase; color: {{ $brandSecondary }};">Approved</div>
                                            </div>
                                        @endif
                                    @else
                                        <div style="height: 30px;">
                                            @if ($signature)
                                                <img src="{{ $signature }}" style="max-height: 28px;">
                                            @endif
                                        </div>
                                        <div style="border-bottom: 1px solid {{ $brandSecondary }}66; font-size: 0; line-height: 0;">&nbsp;</div>
                                        <div style="height: 18px; font-size: 7px; font-weight: bold; text-transform: uppercase; line-height: 1.25; color: {{ $brandSecondary }}; margin-top: 3px;">{{ $role }}</div>
                                        <div style="height: 11px; font-size: 8.5px; font-weight: bold; line-height: 11px; color: #111827;">{{ $name ?: 'N/A' }}</div>
                                        <div style="font-size: 7px; font-weight: bold; color: #111827;">Date: {{ $date ?: '________________' }}</div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    </table>


                </td>
            </tr>
            <tr>
                {{-- Gold on navy, as the reference has it and as the screen
                     card already drew it. The printed one was inheriting the
                     .bar rule's white and quietly disagreeing with the preview
                     a School Admin had approved. 7.19:1 against the navy. --}}
                <td class="bar" style="text-align: center; letter-spacing: 1px; color: {{ $brandAccent }};">
                    {{ $footerMotto ?: $school->name }}
                </td>
            </tr>
        </table>
    </body>
</html>
