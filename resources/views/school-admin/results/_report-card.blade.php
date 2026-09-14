@php
    $brandPrimary = $school->website?->brand_primary_color ?? '#1d4ed8';
    $brandSecondary = $school->website?->brand_secondary_color ?? '#111a35';
    // The two lines of words a school puts on its documents, the tagline
    // under its name, the values along the foot. Set in Settings on every
    // plan; see App\Support\SchoolMotto.
    $schoolMotto = \App\Support\SchoolMotto::for($school);
    $motto = $schoolMotto->tagline;

    // The letterhead address. Read from the school's own settings, which every
    // plan can fill in, it used to come from the website record, so a Basic
    // school's results carried no address at all.
    $letterhead = \App\Support\SchoolContact::for($school);

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

    // The reference card's third colour: the tagline, the corner flourish, the
    // footer motto and the highlighted attendance row.
    //
    // A fixed value, not a school setting, schools currently choose two
    // colours, not three, and pretending to read a third from a column that
    // does not exist would look configurable while never changing. If it
    // should become editable it wants a real column, as the ID card's accent
    // has.
    $brandAccent = '#c8a34a';

    // The same gold, darkened for text on a WHITE ground.
    //
    // #c8a34a is 2.39:1 on white, unreadable as small italic type,
    // and a report card is a document people photocopy. It stays as it
    // is on the navy footer bar (7.19:1 there) and on the rules, which
    // are shapes rather than words. Only the tagline moves, to 5.06:1.
    $brandAccentOnLight = '#8a6a15';

    // The watermark has to read through the panels, not just in the gaps
    // between them, so every block that sits over it is tinted rather than
    // filled. Solid white boxes would punch holes in the crest and leave it
    // showing only in the margins, which is the one thing a watermark must
    // not do.
    $rowPlain = 'rgba(255, 255, 255, 0)';
    $rowStriped = 'rgba(17, 26, 53, 0.035)';

    $footerMotto = $schoolMotto->values;

    // The Principal's signature: this school's School Admin's own registered
    // signature, in gold. Resolved once here rather than per-row, and never
    // from anything in the request, see App\Support\PrincipalSignature.
    $principalSignature = \App\Support\PrincipalSignature::for($school);

    // One place for the contact lines, so the letterhead is a loop rather than
    // four near-identical blocks that drift apart when one is changed.
    $contactLines = collect([
        ['M215.7 499.2C267 435 384 279.4 384 192C384 86 298 0 192 0S0 86 0 192c0 87.4 117 243 168.3 307.2c12.3 15.3 35.1 15.3 47.4 0zM192 128a64 64 0 1 1 0 128 64 64 0 1 1 0-128z', '0 0 384 512', $letterhead->address],
        ['M164.9 24.6c-7.7-18.6-28-28.5-47.4-23.2l-88 24C12.1 30.2 0 46 0 64C0 300.3 211.7 512 448 512c18 0 33.8-12.1 38.6-29.5l24-88c5.3-19.4-4.6-39.7-23.2-47.4l-96-40c-16.3-6.8-35.2-2.1-46.3 11.6L304.7 368C234.3 334.7 177.3 277.7 144 207.3L184.7 167c13.7-11.1 18.4-30 11.6-46.3l-40-96z', '0 0 512 512', $letterhead->phone],
        ['M48 64C21.5 64 0 85.5 0 112c0 15.1 7.1 29.3 19.2 38.4l217.6 163.2c11.4 8.5 27 8.5 38.4 0L492.8 150.4c12.1-9.1 19.2-23.3 19.2-38.4c0-26.5-21.5-48-48-48H48zM0 176V384c0 35.3 28.7 64 64 64H448c35.3 0 64-28.7 64-64V176L294.4 339.2c-22.8 17.1-54 17.1-76.8 0L0 176z', '0 0 512 512', $letterhead->email],
        ['M352 256c0 22.2-1.2 43.6-3.3 64H163.3c-2.2-20.4-3.3-41.8-3.3-64s1.2-43.6 3.3-64H348.7c2.2 20.4 3.3 41.8 3.3 64zM503.9 192c5.3 20.5 8.1 41.9 8.1 64s-2.8 43.5-8.1 64H380.8c2.1-20.6 3.2-42 3.2-64s-1.1-43.4-3.2-64H503.9zM256 0c18 0 45 27 63 82H193C211 27 238 0 256 0zM8.1 192H131.2c-2.1 20.6-3.2 42-3.2 64s1.1 43.4 3.2 64H8.1C2.8 299.5 0 278.1 0 256s2.8-43.5 8.1-64zM256 512c-18 0-45-27-63-82h126c-18 55-45 82-63 82z', '0 0 512 512', $websiteHost],
    ])->filter(fn ($line) => filled($line[2]))->values();
@endphp

<div class="relative mx-auto w-full max-w-3xl overflow-hidden rounded-[5px] bg-white text-left text-gray-900" style="aspect-ratio: 1 / 1.4142; font-family: 'Inter', sans-serif; border: 2.5px solid {{ $brandSecondary }};">
    {{-- No decorative sweep in the top corner. It sat behind the report
         card's own title block and read as a stray mark crowding it rather
         than as a flourish, the corner belongs to the title block. --}}
    {{-- The watermark: this school's OWN crest, not an AkademicNest mark.

         Large enough to read as a watermark rather than a stray graphic, and
         faint enough that nothing printed over it becomes harder to read, the
         whole point of a watermark on a result is that it proves provenance
         without competing with the marks.

         It sits in the page itself rather than beside it, behind every other
         element, and is skipped entirely for a school that has uploaded no
         logo: a grey box in the middle of a report card would be worse than
         no watermark at all. --}}
    @if ($school->logoUrl())
        <div class="pointer-events-none absolute inset-0 z-0 flex items-center justify-center" aria-hidden="true">
            {{-- 76% of the page's width, centred on the whole sheet rather
                 than on any one section, and sized in the style attribute as
                 well as the class so it does not depend on the arbitrary
                 class having reached the compiled stylesheet. --}}
            <img
                src="{{ $school->logoUrl() }}"
                class="w-[76%] max-w-none object-contain"
                style="width: 76%; max-width: none; height: auto; object-fit: contain; opacity: 0.07;"
                alt=""
            >
        </div>
    @endif

    <div class="relative z-10 flex h-full flex-col overflow-hidden">
        <div class="flex-1 space-y-2.5 overflow-y-auto p-4">
            {{-- SEGMENT 1, the letterhead.

                 Crest, then the school's name at the size a letterhead uses,
                 the tagline in the accent colour beneath it, and the contact
                 lines each behind a small round mark. On the right, the card's
                 own title block with the session and term. --}}
            <div class="relative">
                <div class="relative z-10 flex items-start justify-between gap-3 pb-2.5">
                    <div class="flex min-w-0 items-start gap-3">
                        @if ($school->logoUrl())
                            {{-- Sized in the style attribute as well as the
                                 class. A crest is the one image on the page
                                 that ruins the whole letterhead if it renders
                                 at its natural size, and a utility class only
                                 works if that exact class made it into the
                                 compiled stylesheet. --}}
                            <img
                                src="{{ $school->logoUrl() }}"
                                class="h-[120px] w-[120px] shrink-0 object-contain"
                                style="width: 120px; height: 120px; flex-shrink: 0; object-fit: contain;"
                            >
                        @endif

                        <div class="min-w-0 flex-1">
                            <p class="text-[26px] font-extrabold uppercase leading-[1.05]" style="font-size: 26px; line-height: 1.05; color: {{ $brandSecondary }}; font-family: Georgia, 'Times New Roman', serif;">{{ $school->name }}</p>

                            @if ($motto)
                                <p class="mt-0.5 text-[11px] font-bold italic leading-tight" style="color: {{ $brandAccentOnLight }};">{{ $motto }}</p>
                            @endif

                            {{-- Wrapped, never truncated: a letterhead that
                                 cuts the school's own address off mid-word is
                                 worse than one that takes an extra line. The
                                 reference sets them flowing, two to a line
                                 where they fit. --}}
                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-[4px]">
                                @foreach ($contactLines as [$path, $viewBox, $value])
                                    <p class="flex max-w-full items-start gap-1.5 text-[10px] font-bold leading-tight" style="font-size: 10px; font-weight: 700; color: #1f2937;">
                                        <span class="mt-[1px] flex h-[14px] w-[14px] shrink-0 items-center justify-center rounded-full" style="width: 14px; height: 14px; background-color: {{ $brandSecondary }};">
                                            <svg class="h-[8px] w-[8px]" style="width: 8px; height: 8px;" viewBox="{{ $viewBox }}" fill="#ffffff"><path d="{{ $path }}"/></svg>
                                        </span>
                                        <span class="min-w-0">{{ $value }}</span>
                                    </p>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 w-[196px] shrink-0 overflow-hidden rounded-[5px]" style="border: 1.5px solid {{ $brandSecondary }};">
                        <div class="px-2 py-1.5 text-center text-[11px] font-extrabold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">Academic Report Card</div>

                        <div class="bg-white px-2 py-1.5">
                            <div class="flex items-baseline justify-between gap-2 text-[9px]">
                                <span class="font-bold text-gray-900">Academic Session:</span>
                                <span class="font-extrabold" style="color: {{ $brandSecondary }};">{{ $examination->session }}</span>
                            </div>
                            <div class="mt-1 flex items-baseline justify-between gap-2 text-[9px]">
                                <span class="font-bold text-gray-900">Term:</span>
                                <span class="font-extrabold" style="color: {{ $brandSecondary }};">{{ $examination->term->label() }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- The rule under the letterhead: navy running most of the
                     width, finished in the accent colour. --}}
                <div class="flex h-[3px] w-full overflow-hidden rounded-full">
                    <span class="h-full flex-1" style="background-color: {{ $brandSecondary }};"></span>
                    <span class="h-full w-[22%]" style="background-color: {{ $brandAccent }};"></span>
                </div>
            </div>

            {{-- SEGMENT 2, who the card is about.

                 Photograph, then the pupil's details on the left and the
                 result's headline figures on the right, in two label/value
                 columns. The overall grade is a filled pill in the grade's own
                 colour, which is the one figure a parent looks for first. --}}
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

            <div class="flex gap-3 overflow-hidden rounded-[6px] p-3" style="border: 1.5px solid {{ $brandSecondary }}33;">
                <div class="shrink-0">
                    @if ($student->photoUrl())
                        <img src="{{ $student->photoUrl() }}" class="h-[128px] w-[104px] rounded-[4px] object-cover" style="border: 1.5px solid {{ $brandSecondary }};">
                    @else
                        {{-- A silhouette rather than the words "No Photo": the
                             frame is the same size either way, so the card
                             keeps its proportions, and a school looking at the
                             specimen sees the shape of a portrait instead of a
                             blank box that reads as a rendering fault. --}}
                        <span class="flex h-[128px] w-[104px] items-end justify-center overflow-hidden rounded-[4px]" style="width: 104px; height: 128px; background-color: {{ $brandSecondary }}0d; border: 1.5px solid {{ $brandSecondary }}55;">
                            <svg viewBox="0 0 448 512" class="h-[86px] w-[86px]" style="width: 86px; height: 86px;" fill="{{ $brandSecondary }}33" aria-hidden="true">
                                <path d="M224 256a128 128 0 1 0 0-256 128 128 0 1 0 0 256zm-45.7 48C79.8 304 0 383.8 0 482.3 0 498.7 13.3 512 29.7 512H418.3c16.4 0 29.7-13.3 29.7-29.7 0-98.5-79.8-178.3-178.3-178.3H178.3z"/>
                            </svg>
                        </span>
                    @endif
                </div>

                <div class="grid min-w-0 flex-1 grid-cols-2 gap-x-5">
                    @foreach ([$studentFacts, $resultFacts] as $column)
                        <div class="min-w-0 space-y-[5px]">
                            @foreach ($column as [$label, $value])
                                <div class="flex items-baseline gap-2 text-[9.5px]">
                                    @if ($label === '__grade__')
                                        <span class="w-[108px] shrink-0 text-[8.5px] font-bold uppercase" style="color: {{ $brandSecondary }};">Overall Grade:</span>

                                        {{-- Letter and description sit INSIDE the
                                             one filled block, as the reference has
                                             them, not a coloured letter with grey
                                             text beside it. --}}
                                        <span class="inline-flex min-w-0 items-baseline gap-2 rounded-[3px] px-2.5 py-[3px] text-white" style="background-color: {{ $overallColor }};">
                                            <span class="text-[12px] font-extrabold leading-none">{{ $overallGrade }}</span>
                                            @if ($overallDescription)
                                                <span class="truncate text-[9px] font-bold uppercase leading-none">({{ $overallDescription }})</span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="w-[108px] shrink-0 text-[8.5px] font-bold uppercase leading-tight" style="color: {{ $brandSecondary }};">{{ $label }}:</span>
                                        <span class="min-w-0 truncate text-[9.5px] font-semibold text-gray-900">{{ filled($value) ? $value : 'N/A' }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- SEGMENT 3, the marks.

                 One row per subject: the test and exam marks the school
                 recorded, their total, the percentage that follows from it,
                 the grade in the grade's own colour and the remark that goes
                 with that band.

                 The column headings carry their own maximum, "Test (40
                 Marks)", because a school can set those maxima per paper, and
                 a mark of 32 means nothing without knowing 32 out of what. --}}
            <div class="overflow-hidden rounded-[6px]" style="border: 1.5px solid {{ $brandSecondary }};">
                <div class="py-1.5 text-center text-[11px] font-extrabold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">Academic Performance</div>

                <table class="w-full border-collapse text-left text-[9px]">
                    <thead>
                        <tr class="text-white" style="background-color: {{ $brandSecondary }};">
                            @foreach ([
                                ['S/N', 'w-[26px] text-center'],
                                ['Subject', ''],
                                ['Test', 'w-[62px] text-center', $testMaxLabel.' Marks'],
                                ['Exam', 'w-[62px] text-center', $examMaxLabel.' Marks'],
                                ['Total', 'w-[62px] text-center', $subjectMaxLabel.' Marks'],
                                ['Percentage', 'w-[68px] text-center', '%'],
                                ['Grade', 'w-[52px] text-center'],
                                ['Remark', 'w-[92px] text-center'],
                            ] as $heading)
                                <th class="{{ $heading[1] }} px-1.5 py-1.5 align-middle text-[8.5px] font-extrabold uppercase leading-tight" style="border: 0.5px solid rgba(255,255,255,0.25);">
                                    {{ $heading[0] }}
                                    @isset($heading[2])
                                        <span class="block text-[7px] font-semibold normal-case opacity-80">({{ $heading[2] }})</span>
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
                                $rowColor = $colorForPercentage($percentage);
                            @endphp
                            <tr style="background-color: {{ $loop->even ? $rowStriped : $rowPlain }};">
                                <td class="border border-gray-200 px-1.5 py-[5px] text-center font-semibold text-gray-900">{{ $loop->iteration }}</td>
                                <td class="border border-gray-200 px-2 py-[5px] font-semibold text-gray-900">{{ $subject->name }}</td>
                                <td class="border border-gray-200 px-1.5 py-[5px] text-center font-semibold text-gray-900">{{ $score?->test_score !== null ? rtrim(rtrim($score->test_score, '0'), '.') : 'N/A' }}</td>
                                <td class="border border-gray-200 px-1.5 py-[5px] text-center font-semibold text-gray-900">{{ $score?->exam_score !== null ? rtrim(rtrim($score->exam_score, '0'), '.') : 'N/A' }}</td>
                                <td class="border border-gray-200 px-1.5 py-[5px] text-center font-extrabold text-gray-900">{{ $score ? rtrim(rtrim($score->score, '0'), '.') : 'N/A' }}</td>
                                <td class="border border-gray-200 px-1.5 py-[5px] text-center font-semibold text-gray-900">{{ $percentage !== null ? $percentage.'%' : 'N/A' }}</td>
                                <td class="border border-gray-200 px-1.5 py-[5px] text-center text-[11px] font-extrabold" style="color: {{ $rowColor }};">{{ $score ? $score->grade() : 'N/A' }}</td>
                                <td class="border border-gray-200 px-1.5 py-[5px] text-center font-semibold text-gray-900">{{ $percentage !== null ? \App\Models\GradeBand::describe($school, $percentage) : 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>

                    <tfoot>
                        <tr class="font-extrabold" style="background-color: {{ $brandSecondary }}12; color: {{ $brandSecondary }};">
                            <td colspan="2" class="border border-gray-300 px-2 py-1.5 uppercase">Total</td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-center">{{ rtrim(rtrim((string) $totalTest, '0'), '.') }}</td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-center">{{ rtrim(rtrim((string) $totalExam, '0'), '.') }}</td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-center">{{ rtrim(rtrim((string) $totalScore, '0'), '.') }}/{{ $totalMax }}</td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-center">{{ $summary['average'] !== null ? $summary['average'].'%' : 'N/A' }}</td>
                            <td class="border border-gray-300 px-1.5 py-1.5 text-center text-[11px]" style="color: {{ $overallColor }};">{{ $overallGrade }}</td>
                            <td class="border border-gray-300 px-1.5 py-1.5"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- SEGMENT 4, three panels reading the same result three ways.

                 The summary is the headline figures gathered in one place; the
                 grade key is the school's OWN bands, not a fixed A-E, so a
                 parent can check the letter against the scale that produced
                 it; the attendance record is the term's register.

                 Each panel is a heading over label/value rows, with the row
                 that matters most in each picked out in the accent colour. --}}
            @php
                $summaryRows = [
                    ['Total Marks Obtained', rtrim(rtrim((string) $totalScore, '0'), '.').' / '.$totalMax, false],
                    ['Average Score', $summary['average'] !== null ? $summary['average'].'%' : 'N/A', false],
                    ['Overall Grade', $overallGrade.($overallDescription ? ' ('.strtoupper($overallDescription).')' : ''), false, $overallColor],
                    ['Position in Class', $positionLabel, false],
                    ['Number in Class', (string) $numberInClass, false],
                    ['Attendance Percentage', $attendance && $attendance['percent'] !== null ? $attendance['percent'].'%' : 'N/A', true],
                ];

                $attendanceRows = $attendance ? [
                    ['Total School Days', (string) $attendance['total'], false],
                    ['Days Present', (string) $attendance['present'], false],
                    ['Days Absent', (string) $attendance['absent'], false],
                    ['Days Late', (string) $attendance['late'], false],
                    ['Excused Absences', (string) $attendance['excused'], false],
                    ['Attendance Percentage', $attendance['percent'] !== null ? $attendance['percent'].'%' : 'N/A', true],
                ] : [];
            @endphp

            <div class="grid grid-cols-3 gap-2.5">
                @foreach ([
                    ['Summary of Performance', $summaryRows],
                    ['__gradekey__', []],
                    ['Attendance Record', $attendanceRows],
                ] as [$title, $rows])
                    <div class="overflow-hidden rounded-[6px]" style="border: 1.5px solid {{ $brandSecondary }};">
                        <div class="py-1.5 text-center text-[9px] font-extrabold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">
                            {{ $title === '__gradekey__' ? 'Grade Key (By Percentage)' : $title }}
                        </div>

                        @if ($title === '__gradekey__')
                            <table class="w-full border-collapse text-[7.5px]">
                                <thead>
                                    <tr class="uppercase" style="background-color: {{ $brandSecondary }}12; color: {{ $brandSecondary }};">
                                        <th class="border border-gray-200 px-1 py-1 text-center font-extrabold">Grade</th>
                                        <th class="border border-gray-200 px-1 py-1 text-center font-extrabold">Percentage Range</th>
                                        <th class="border border-gray-200 px-1 py-1 text-center font-extrabold">Remark</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($gradeKey as $band)
                                        <tr style="background-color: {{ $loop->even ? $rowStriped : $rowPlain }};">
                                            <td class="border border-gray-200 px-1 py-[3px] text-center text-[10px] font-extrabold" style="color: {{ $gradeColors[$loop->index % count($gradeColors)] }};">{{ $band['letter'] }}</td>
                                            <td class="whitespace-nowrap border border-gray-200 px-1 py-[3px] text-center font-semibold text-gray-900">{{ $band['min_percent'] }} &ndash; {{ $band['max_percent'] }}</td>
                                            <td class="border border-gray-200 px-1 py-[3px] text-center font-semibold text-gray-900">{{ $band['description'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @elseif ($rows === [])
                            {{-- Deliberately "term dates not set" and not "no
                                 attendance was taken": the register may be
                                 complete and only the term's dates missing, and
                                 telling a parent their child was absent all
                                 term because a School Admin skipped a settings
                                 field would be a lie. --}}
                            <div class="p-1.5 text-[8.5px] leading-[1.4] font-semibold text-gray-700">
                                Term dates not set
                                <span class="block">Add this term's start and end dates under Academics &rarr; Terms to show attendance here.</span>
                            </div>
                        @else
                            <table class="w-full border-collapse text-[8px]">
                                <tbody>
                                    @foreach ($rows as $row)
                                        @php [$label, $value, $highlight] = $row; @endphp
                                        <tr @style(['background-color: #f6ecd2' => $highlight, 'background-color: '.($loop->even ? $rowStriped : $rowPlain) => ! $highlight])>
                                            <td class="border border-gray-200 px-1.5 py-[4px] font-bold uppercase" style="color: {{ $highlight ? $brandSecondary : $brandSecondary }};">{{ $label }}:</td>
                                            <td class="border border-gray-200 px-1.5 py-[4px] text-right font-extrabold" style="color: {{ $row[3] ?? ($highlight ? $brandSecondary : '#111827') }};">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- SEGMENT 5, what the people who taught the child think.

                 Set in italic and given room to breathe: these are the two
                 lines most parents read first, and the only part of the card
                 written by a person rather than computed from marks. --}}
            <div class="overflow-hidden rounded-[6px]" style="border: 1.5px solid {{ $brandSecondary }};">
                <div class="py-1.5 text-center text-[11px] font-extrabold uppercase tracking-wide text-white" style="background-color: {{ $brandSecondary }};">Remarks</div>

                <table class="w-full border-collapse text-[9px]">
                    <tbody>
                        @foreach ([
                            ["Class Teacher's Remark", $report->teacher_remark],
                            ["Principal's Remark", $report->principal_remark],
                        ] as $index => [$label, $remark])
                            <tr style="background-color: {{ $index === 0 ? $rowPlain : $rowStriped }};">
                                <td class="w-[150px] border border-gray-200 px-2 py-2 align-top text-[8.5px] font-extrabold uppercase" style="color: {{ $brandSecondary }};">{{ $label }}:</td>
                                <td class="border border-gray-200 px-2.5 py-2 italic leading-snug text-gray-900">{{ $remark ?: 'N/A' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- SEGMENT 6, the hands that sign it.

             Class teacher and principal each over their own rule, the school's
             approval stamp between them, and the parent's line left blank with
             a date to fill in, the one thing on the card the school does not
             complete itself.

             Each signature comes from the person it belongs to: the class
             teacher's from their own staff record, which they upload in their
             portal, and the principal's from their own School Admin account.
             Neither is drawn for them, so an unsigned line means genuinely
             unsigned.

             THE GEOMETRY IS FIXED, AND THAT IS THE POINT. Every block is the
             same height and every part inside it, the space the signature
             occupies, the ruled line, the role, the name, the date, is a
             fixed height too. So the Principal's line is in exactly the same
             place on every card this school ever issues: whether they have
             registered a signature or not, whether the name under it is short
             or long, whether the label wraps to one line or two.

             It used to be `items-end`, which bottom-aligned four blocks of
             different heights, a two-line label under one and a one-line
             label under another put their ruled lines at different heights,
             and the Principal's signature drifted up and down the page
             depending on how long somebody's name was. --}}
        <div class="shrink-0 px-4 pb-1 pt-2">
            <div class="grid grid-cols-4 items-start gap-4">
                @foreach ([
                    ["Class Teacher's Signature", $classTeacher?->staff?->fullName(), now()->format('jS F, Y'), $classTeacher?->staff?->signatureDataUri()],

                    {{-- School Admin is the Principal, so this is their own
                         registered signature, resolved from THIS school's
                         admin account and rendered in gold. --}}
                    ["Principal's Signature", $school->principalName(), now()->format('jS F, Y'), $principalSignature?->dataUri()],
                    ['__stamp__', null, null, null],
                    ["Parent / Guardian's Signature", $student->guardian_name, null, null],
                ] as [$role, $name, $date, $signature])
                    @if ($role === '__stamp__')
                        {{-- The same overall height as a signer's block, so
                             the stamp sits between them rather than dragging
                             the row out of line. --}}
                        <div class="flex h-[76px] items-center justify-center" style="height: 76px;">
                            @if ($school->hasStamp())
                                {{-- The school's REAL stamp, on transparency,
                                     so it sits over the card. Embedded rather
                                     than linked, a stamp is what makes a
                                     document look official, so there is no
                                     address that hands out a clean copy. --}}
                                <img
                                    src="{{ $school->stampDataUri() }}"
                                    alt="{{ $school->name }} official stamp"
                                    class="max-h-[70px] max-w-full object-contain"
                                    style="max-height: 70px; object-fit: contain;"
                                >
                            @else
                                {{-- No stamp uploaded. The drawn ring keeps the
                                     block the same shape, so a card from a
                                     school that has not uploaded one is laid
                                     out identically to one that has. --}}
                                <div class="flex h-[62px] w-[62px] flex-col items-center justify-center rounded-full text-center" style="width: 62px; height: 62px; border: 2.5px double {{ $brandSecondary }};">
                                    @if ($school->logoUrl())
                                        <img src="{{ $school->logoUrl() }}" alt="" class="h-[22px] w-[22px] object-contain" style="width: 22px; height: 22px;">
                                    @endif
                                    <span class="mt-[1px] text-[5.5px] font-extrabold uppercase tracking-wide" style="color: {{ $brandSecondary }};">Approved</span>
                                </div>
                            @endif
                        </div>
                    @else
                        <div class="h-[76px] text-center" style="height: 76px;">
                            {{-- Reserved whether or not anything is in it. An
                                 unsigned card is the same shape as a signed
                                 one, so the ruled line never moves. --}}
                            <div class="flex h-[30px] items-end justify-center" style="height: 30px;">
                                @if ($signature)
                                    <img src="{{ $signature }}" alt="" class="mx-auto max-h-[28px] object-contain" style="max-height: 28px; object-fit: contain;">
                                @endif
                            </div>

                            <div style="border-bottom: 1px solid {{ $brandSecondary }}66;"></div>

                            {{-- Named in full, "Class Teacher's Signature",
                                 not "Class Teacher", so each block says what
                                 it is rather than who it is. Two lines' worth
                                 of height is reserved at 7px, so a label that
                                 wraps and one that does not still put the name
                                 beneath them on the same line. --}}
                            <p class="mt-1 h-[18px] text-[7px] font-extrabold uppercase leading-[1.25] tracking-wide" style="height: 18px; font-size: 7px; color: {{ $brandSecondary }};">{{ $role }}</p>
                            <p class="h-[11px] truncate text-[8.5px] font-bold leading-[11px] text-gray-900" style="height: 11px;">{{ $name ?: 'N/A' }}</p>
                            <p class="text-[7px] font-semibold leading-tight text-gray-900">Date: {{ $date ?: '________________' }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        {{-- The motto along the foot: gold on navy, as the reference has it. --}}
        <div class="shrink-0 py-1.5 text-center text-[8.5px] font-bold uppercase tracking-widest" style="color: {{ $brandAccent }}; background-color: {{ $brandSecondary }};">
            {{ $footerMotto ?: $school->name }}
        </div>
    </div>
</div>
