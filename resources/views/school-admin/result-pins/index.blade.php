@php
    $examinationOptions = $examinations->mapWithKeys(fn ($examination) => [
        $examination->id => "{$examination->name}, {$examination->class_name} ({$examination->term->label()}, {$examination->session})",
    ]);
@endphp

<x-dashboard-layout
    page-title="Generate Exam Token"
    page-subtitle="Each token is 15 characters and opens one student's result for one term. Issue them, then give each parent the token for their own child."
>
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-[5px] bg-red-50 p-4 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400 lg:rounded-[10px]">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- The school's own result-checking address.
             Half of what a parent needs: this link decides WHICH school's
             tokens are even considered, and the token decides which student. --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-5 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Your result-checking link</h2>
                <small class="field-hint mt-0.5">
                    Share this with parents alongside each student's token. It only ever opens results for this school &mdash;
                    a token from another school will not work here.
                </small>
            </div>

            <div class="space-y-4 p-5">
                <div x-data="{ copied: false }" class="flex flex-wrap items-center gap-3">
                    <code
                        x-ref="resultLink"
                        class="min-w-0 flex-1 truncate rounded-[8px] border border-gray-200 bg-gray-50 px-3 py-2.5 font-mono text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200"
                    >{{ $resultLinkUrl }}</code>

                    <button
                        type="button"
                        @click="navigator.clipboard.writeText($refs.resultLink.textContent.trim()).then(() => { copied = true; setTimeout(() => copied = false, 2000) })"
                        class="icon-btn icon-btn--download"
                        data-tooltip="Copy link"
                        aria-label="Copy link"
                        :data-tooltip="copied ? 'Copied' : 'Copy link'"
                        :aria-label="copied ? 'Copied' : 'Copy link'"
                    >
                        <span x-show="! copied"><i class="fa-solid fa-copy" aria-hidden="true"></i></span>
                        <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                    </button>
                </div>

                @if (! $school->resultLinkIsLive())
                    <div class="rounded-[8px] bg-amber-50 p-3 text-xs font-medium text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                        This link is switched off. Parents opening it see nothing at all until you turn it back on.
                        Tokens you have already issued are unaffected.
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-4 dark:border-gray-700">
                    <form method="POST" action="{{ route('result-pins.link.toggle') }}">
                        @csrf
                        <button type="submit" class="btn rounded-[8px] bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300">
                            <i class="fa-solid {{ $school->resultLinkIsLive() ? 'fa-toggle-off' : 'fa-toggle-on' }} btn-icon" aria-hidden="true"></i> {{ $school->resultLinkIsLive() ? 'Switch off' : 'Switch back on' }}
                        </button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('result-pins.link.regenerate') }}"
                        onsubmit="return confirm('Generate a new link? The current one stops working immediately and can never be reused, so every parent will need the new address. Tokens already issued keep working.')"
                    >
                        @csrf
                        <button type="submit" class="btn rounded-[8px] bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300">
                            <i class="fa-solid fa-arrows-rotate btn-icon" aria-hidden="true"></i> Generate a new link
                        </button>
                    </form>

                    <small class="block text-xs text-gray-400 dark:text-gray-500">
                        Switching off is reversible. Generating a new link is not &mdash; the old address is retired permanently.
                    </small>
                </div>
            </div>
        </div>

        {{-- Freshly issued tokens, shown once. After this page is left they can
             only be recovered one at a time through "Show token", which is
             audited, so this is the moment to print or copy them. --}}
        @if (! empty($issuedTokens))
            <div class="rounded-[5px] border-2 border-green-300 bg-green-50 p-6 dark:border-green-800 dark:bg-green-900/20 lg:rounded-[10px]">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-sm font-bold text-green-900 dark:text-green-300">Tokens</h2>
                        <p class="mt-0.5 text-xs text-green-800 dark:text-green-400">
                            Copy or print these now. They are not shown again until you ask for them,
                            with Reveal on a student's row or by running the class batch again.
                        </p>
                    </div>
                    <button type="button" onclick="window.print()" class="icon-btn icon-btn--download print:hidden" data-tooltip="Print list" aria-label="Print list">
                        <i class="fa-solid fa-print" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="mt-4 overflow-x-auto">
                    <table class="min-w-full text-left text-sm">
                        <thead class="text-xs uppercase text-green-900/70 dark:text-green-400/70">
                            <tr>
                                <th class="py-2 pr-4 font-semibold">Student</th>
                                <th class="py-2 pr-4 font-semibold">Result</th>
                                <th class="py-2 font-semibold">Token</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-green-200 dark:divide-green-900">
                            @foreach ($issuedTokens as $issued)
                                <tr>
                                    <td class="py-2 pr-4">
                                        <p class="font-semibold text-gray-900 dark:text-white">{{ $issued['student'] }}</p>
                                        <small class="field-hint">{{ $issued['admission_number'] }}</small>
                                    </td>
                                    <td class="py-2 pr-4 text-xs text-gray-600 dark:text-gray-300">
                                        {{ $issued['examination'] }}<br>{{ $issued['term'] }} &middot; {{ $issued['session'] }}
                                    </td>
                                    <td class="py-2 font-mono text-sm font-bold tracking-wider text-gray-900 dark:text-white">{{ $issued['token'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            {{-- One student --}}
            <form method="POST" action="{{ route('result-pins.store') }}" class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                @csrf
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Issue one token</h2>
                <small class="field-hint mt-0.5">
                    For a single student and a single result.
                </small>

                {{-- Academic year, then term, then the examination inside it,
                     then the student. That is the order a School Admin thinks
                     in at the end of a term, and narrowing by year and term
                     first turns a list of every examination the school has
                     ever run into a list of two or three.

                     The narrowing is a convenience only. What is submitted is
                     an examination id and a student id, and the server checks
                     both belong to this school and to each other before it
                     will issue anything, see ResultTokenIssuer. --}}
                <div
                    class="mt-4 space-y-3"
                    x-data="{
                        {{-- The school's academic year from Settings, not the
                             newest examination's. A school that has moved on
                             in Settings was still being defaulted to the year
                             its last examination was filed under. --}}
                        session: @js(old('session', $currentSession)),
                        term: @js(old('term', '')),
                        examinations: @js($examinations->map(fn ($exam) => [
                            'id' => $exam->id,
                            'session' => $exam->session,
                            'term' => $exam->term->value,
                            'label' => $exam->name.', '.$exam->class_name,
                        ])->values()),
                        get available() {
                            return this.examinations.filter(
                                (exam) => exam.session === this.session && (this.term === '' || exam.term === this.term),
                            )
                        },
                    }"
                >
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="session" class="field-label mb-1">Academic Year</label>
                            <select id="session" name="session" x-model="session" required class="w-full">
                                @foreach ($sessions as $session)
                                    <option value="{{ $session }}">{{ $session }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="term" class="field-label mb-1">Term</label>
                            <select id="term" name="term" x-model="term" required class="w-full">
                                <option value="">All terms</option>
                                @foreach ($terms as $term)
                                    <option value="{{ $term->value }}">{{ $term->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- No Examination field. The year, the term and the
                         student's own class name it completely, so the server
                         works it out, see App\Services\ExaminationResolver.
                         Asking for it here meant a school with none recorded
                         met an empty dropdown and could issue nothing. --}}

                    <div>
                        <label for="student_id" class="field-label mb-1">Student / Pupil</label>
                        <select id="student_id" name="student_id" required class="w-full">
                            <option value="">Choose a student…</option>
                            @foreach ($students as $student)
                                <option value="{{ $student->id }}" @selected(old('student_id') == $student->id)>
                                    {{ $student->fullName() }} to {{ $student->class_name }} ({{ $student->admission_number }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Said where the token is issued, because this is where a
                     School Admin forms the expectation that handing over a
                     token hands over the result. It does not, if fees are
                     owed. --}}
                <div class="mt-3 flex items-start gap-2 rounded-[8px] border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/50 dark:bg-amber-900/20">
                    <i class="fa-solid fa-lock mt-0.5 text-[11px] text-amber-600 dark:text-amber-400"></i>
                    <p class="text-[11px] leading-[1.5] text-amber-800 dark:text-amber-300">
                        A token opens a result only when the student's fees are settled, or when you have
                        released that term's results for them. The token stays valid either way.
                    </p>
                </div>

                <button type="submit" class="btn mt-4 w-full rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i class="fa-solid fa-hand-holding btn-icon" aria-hidden="true"></i> Issue token
                </button>
            </form>

            {{-- A whole class --}}
            <form method="POST" action="{{ route('result-pins.store-bulk') }}" class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                @csrf
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Issue for a whole class</h2>
                <small class="field-hint mt-0.5">
                    One token per student in the examination's class. Students who already hold a
                    token for this result are skipped, so running it twice is safe.
                </small>

                {{-- The class is chosen from the school's own class list, never
                     typed. A typed class name is how a school ends up with its
                     students filed under "SSS 1 Science" and a batch issued
                     against "SSS1 Science", two classes, one empty, and no
                     obvious reason why the batch came out at zero. The server
                     validates it against the same list. --}}
                <div
                    class="mt-4 space-y-3"
                    x-data="{
                        className: @js(old('class_name', '')),
                        {{-- The school's academic year from Settings, not the
                             newest examination's. A school that has moved on
                             in Settings was still being defaulted to the year
                             its last examination was filed under. --}}
                        session: @js(old('session', $currentSession)),
                        term: @js(old('term', '')),
                        classCounts: @js($classCounts),
                        examinations: @js($examinations->map(fn ($exam) => [
                            'id' => $exam->id,
                            'session' => $exam->session,
                            'term' => $exam->term->value,
                            'class' => $exam->class_name,
                            'label' => $exam->name,
                        ])->values()),
                        get available() {
                            return this.examinations.filter((exam) =>
                                exam.class === this.className
                                && exam.session === this.session
                                && (this.term === '' || exam.term === this.term))
                        },
                        get studentCount() {
                            return this.classCounts[this.className] ?? 0
                        },
                    }"
                >
                    <div>
                        <label for="bulk_class_name" class="field-label mb-1">Class</label>
                        <select id="bulk_class_name" name="class_name" x-model="className" required class="w-full">
                            <option value="">Choose a class…</option>
                            @foreach ($classNames as $className)
                                <option value="{{ $className }}">{{ $className }}</option>
                            @endforeach
                        </select>
                        <small class="field-hint mt-1" x-show="className !== ''" x-cloak>
                            <span x-text="studentCount"></span> active student<span x-show="studentCount !== 1">s</span>
                            in this class &mdash; one token will be generated for each.
                        </small>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="bulk_session" class="field-label mb-1">Academic Year</label>
                            <select id="bulk_session" name="session" x-model="session" required class="w-full">
                                @foreach ($sessions as $session)
                                    <option value="{{ $session }}">{{ $session }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="bulk_term" class="field-label mb-1">Term</label>
                            <select id="bulk_term" name="term" x-model="term" required class="w-full">
                                <option value="">All terms</option>
                                @foreach ($terms as $term)
                                    <option value="{{ $term->value }}">{{ $term->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- No Examination field here either: class, year and term
                         name it. See App\Services\ExaminationResolver. --}}
                </div>

                <button type="submit" class="btn mt-4 w-full rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i class="fa-solid fa-wand-magic-sparkles btn-icon" aria-hidden="true"></i> Generate tokens for this class
                </button>
            </form>
        </div>

        {{-- Bulk: hand-picked students across several classes, reviewed,
             generated as a batch and printed on A4. --}}
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-[5px] border-2 border-primary-200 bg-white p-6 shadow-sm dark:border-primary-900 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-start gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary-100 text-primary-700 dark:bg-primary-900/50 dark:text-primary-300">
                        <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
                    </span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-bold text-gray-900 dark:text-white">Bulk exam tokens</h2>
                        <small class="field-hint mt-0.5">
                            Pick students from several classes at once (for example 10 from Basic 5, 15 from Basic 6 and
                            20 from JSS 1), review them, generate their tokens and print them on A4.
                        </small>
                    </div>
                </div>
                <a href="{{ route('result-pins.bulk.create') }}" class="btn mt-4 inline-flex w-full items-center justify-center rounded-[8px] bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                    <i class="fa-solid fa-users-rectangle btn-icon" aria-hidden="true"></i> Choose Students
                </a>

                @if ($recentBatches->isNotEmpty())
                    <div class="mt-5 border-t border-gray-100 pt-4 dark:border-gray-700">
                        <small class="block text-xs font-semibold uppercase tracking-wide text-gray-500">Recent batches</small></small>
                        <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($recentBatches as $batch)
                                <li class="flex items-center justify-between gap-3 py-2 text-sm">
                                    <span class="min-w-0">
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $batch->token_count }} token{{ $batch->token_count === 1 ? '' : 's' }}</span>
                                        <span class="text-xs text-gray-500">&middot; {{ $batch->term->label() }}, {{ $batch->session }} &middot; {{ $batch->created_at->format('j M Y') }}</span>
                                    </span>
                                    <a href="{{ route('result-pins.batches.show', $batch) }}" class="shrink-0 text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                        <i class="fa-solid fa-print text-[10px]" aria-hidden="true"></i> Open &amp; print
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            {{-- Every valid token for a term, however it was issued: one at a
                 time, by whole class, or in a batch. --}}
            <form method="POST" action="{{ route('result-pins.print') }}" target="_blank" x-data="{ download: 0 }" class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                @csrf
                <input type="hidden" name="download" :value="download">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Print tokens for a term</h2>
                <small class="field-hint mt-0.5">
                    Every valid token for the term, however it was issued, on A4 with each student's name, admission number and class.
                </small>

                <div class="mt-4 grid grid-cols-2 gap-3">
                    <div>
                        <label for="print_session" class="field-label mb-1">Academic Year</label>
                        <select id="print_session" name="session" required class="w-full">
                            @foreach ($sessions as $session)
                                <option value="{{ $session }}" @selected($session === $currentSession)>{{ $session }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="print_term" class="field-label mb-1">Term</label>
                        <select id="print_term" name="term" required class="w-full">
                            @foreach ($terms as $term)
                                <option value="{{ $term->value }}">{{ $term->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="print_class_name" class="field-label mb-1">Class</label>
                    <select id="print_class_name" name="class_name" class="w-full">
                        <option value="">All classes</option>
                        @foreach ($classNames as $className)
                            <option value="{{ $className }}">{{ $className }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
                    <button type="submit" x-on:click="download = 0" class="btn flex-1 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <i class="fa-solid fa-print btn-icon" aria-hidden="true"></i> Print
                    </button>
                    <button type="submit" x-on:click="download = 1" class="btn flex-1 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200">
                        <i class="fa-solid fa-file-arrow-down btn-icon" aria-hidden="true"></i> Download PDF
                    </button>
                </div>
            </form>
        </div>

        {{-- Tracking, one class at a time.

             Built from the STUDENTS in the class rather than from the tokens,
             so a student who has no token yet shows as a gap. "28 issued of 30
             students" is the fact a School Admin needs at the end of term, and
             it cannot be seen in a list that only contains tokens. --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Track a class</h2>
                    <small class="field-hint mt-0.5">
                        Who has used their token, who has not, and when.
                    </small>
                </div>

                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <select name="track_examination_id" onchange="this.form.submit()" class="w-64">
                        <option value="">Choose a class result…</option>
                        @foreach ($examinationOptions as $id => $label)
                            <option value="{{ $id }}" @selected(request('track_examination_id') == $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            @if ($classTracking)
                @php($totals = $classTracking['totals'])

                <div class="grid grid-cols-2 gap-3 p-6 sm:grid-cols-5">
                    @foreach ([
                        ['Students in class', $totals['students'], 'text-gray-900 dark:text-white'],
                        ['Tokens generated', $totals['issued'], 'text-blue-600 dark:text-blue-400'],
                        ['Used', $totals['used'], 'text-green-600 dark:text-green-400'],
                        ['Not yet used', $totals['unused'], 'text-amber-600 dark:text-amber-400'],
                        ['No token yet', $totals['missing'], 'text-red-600 dark:text-red-400'],
                    ] as [$label, $value, $tone])
                        <div class="rounded-[8px] border border-gray-200 p-3 dark:border-gray-700">
                            <small class="field-hint">{{ $label }}</small>
                            <p class="mt-1 text-2xl font-extrabold {{ $tone }}">{{ number_format($value) }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[46rem] text-left text-sm">
                        <thead class="border-y border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="w-12 px-4 py-3 font-semibold">S/N</th>
                                <th class="px-6 py-3 font-semibold">Student / Pupil</th>
                                <th class="px-6 py-3 font-semibold">Token</th>
                                <th class="px-6 py-3 font-semibold">Status</th>
                                <th class="px-6 py-3 font-semibold">Used by</th>
                                <th class="px-6 py-3 font-semibold">When</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($classTracking['rows'] as $row)
                                <tr>
                                    <td class="w-12 px-4 py-3 tabular-nums text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="px-6 py-3">
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $row['student']->fullName() }}</span>
                                        <small class="block text-xs text-gray-400">{{ $row['student']->admission_number }}</small>
                                    </td>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">
                                        {{-- Never the token itself. It is stored
                                             hashed, and a screen that reprinted
                                             every token in a class would be the
                                             one place they could all be taken
                                             from at once. "Reveal" on a single
                                             row is the way back to one. --}}
                                        @if ($row['token'])
                                            <span class="rounded-full bg-gray-100 px-2 py-0.5 font-mono text-[11px] dark:bg-gray-700">
                                                Issued
                                            </span>
                                        @else
                                            &mdash;
                                        @endif
                                    </td>
                                    <td class="px-6 py-3">
                                        @if (! $row['token'])
                                            <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs font-bold text-red-700 dark:bg-red-900/30 dark:text-red-400">No token</span>
                                        @elseif ($row['used'])
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-bold text-green-700 dark:bg-green-900/30 dark:text-green-400">
                                                Used{{ $row['uses_count'] > 1 ? ' ×'.$row['uses_count'] : '' }}
                                            </span>
                                        @else
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Not used</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $row['used_by'] ?? 'N/A' }}</td>
                                    <td class="px-6 py-3 text-gray-500 dark:text-gray-400">
                                        {{ $row['used_at']?->format('j M Y, g:ia') ?? 'N/A' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <small class="field-hint p-6">Choose a class result above to see who has used their token.</small>
            @endif
        </div>

        {{-- Issued tokens.

             Delete in three ways: the bin on one row, the ticked rows
             together, or every token in a category (class, result, session,
             term, status or student) across all pages. The bulk actions share
             one confirmation box that says exactly what will go. --}}
        <div
            class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]"
            x-data="{
                selected: [],
                names: @js($tokens->getCollection()->mapWithKeys(fn ($t) => [
                    $t->uuid => trim(($t->boundStudent?->fullName() ?? 'Unbound token').($t->examination ? ' · '.$t->examination->name.', '.$t->examination->term->label().' '.$t->examination->session : '')),
                ])),
                confirming: null,
                get pageIds() { return Object.keys(this.names) },
                get allSelected() { return this.pageIds.length > 0 && this.selected.length === this.pageIds.length },
                get someSelected() { return this.selected.length > 0 && ! this.allSelected },
                toggleAll(on) { this.selected = on ? [...this.pageIds] : [] },
            }"
            @keydown.escape.window="confirming = null"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Issued tokens</h2>
                    <small class="field-hint mt-0.5">
                        Each row is one student and one result. A token never opens anything else.
                        Tick tokens to send their result details to parents, or to delete them together. Filter by a category to act on every matching token.
                    </small>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-600 dark:bg-gray-700 dark:text-gray-300">
                        {{ number_format($tokens->total()) }} {{ $tokens->total() === 1 ? 'token' : 'tokens' }}{{ $hasTokenFilter ? ' match' : '' }}
                    </span>
                    <a href="{{ route('result-pins.details.history') }}" class="btn inline-flex items-center gap-1.5 rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                        <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i> Sending history
                    </a>

                    {{-- Every valid token for the filtered term (and class),
                         to their parents and guardians. --}}
                    @if (filled($tokenFilters['session']) && filled($tokenFilters['term']) && $tokens->total() > 0)
                        <x-send-result-details :action="route('result-pins.details.preview')" label="Send for all matching">
                            <input type="hidden" name="session" value="{{ $tokenFilters['session'] }}">
                            <input type="hidden" name="term" value="{{ $tokenFilters['term'] }}">
                            @if (filled($tokenFilters['class_name']))
                                <input type="hidden" name="class_name" value="{{ $tokenFilters['class_name'] }}">
                            @endif
                        </x-send-result-details>
                    @endif
                </div>
            </div>

            {{-- Filters. Each one is a category the admin can delete in one go. --}}
            <form method="GET" action="{{ route('result-pins.index') }}" class="grid gap-3 border-b border-gray-100 p-6 dark:border-gray-700 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div class="xl:col-span-2">
                    <label for="token_search" class="field-label mb-1">Student</label>
                    <input id="token_search" type="search" name="search" value="{{ $tokenFilters['search'] }}" maxlength="100" placeholder="Name or admission number" class="w-full">
                </div>
                <div>
                    <label for="token_class" class="field-label mb-1">Class</label>
                    <select id="token_class" name="class_name" onchange="this.form.submit()" class="w-full">
                        <option value="">All classes</option>
                        @foreach ($classNames as $className)
                            <option value="{{ $className }}" @selected($tokenFilters['class_name'] === $className)>{{ $className }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="token_session" class="field-label mb-1">Session</label>
                    <select id="token_session" name="session" onchange="this.form.submit()" class="w-full">
                        <option value="">All sessions</option>
                        @foreach ($sessions as $session)
                            <option value="{{ $session }}" @selected($tokenFilters['session'] === $session)>{{ $session }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="token_term" class="field-label mb-1">Term</label>
                    <select id="token_term" name="term" onchange="this.form.submit()" class="w-full">
                        <option value="">All terms</option>
                        @foreach ($terms as $term)
                            <option value="{{ $term->value }}" @selected($tokenFilters['term'] === $term->value)>{{ $term->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="token_status" class="field-label mb-1">Status</label>
                    <select id="token_status" name="status" onchange="this.form.submit()" class="w-full">
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($tokenFilters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2 lg:col-span-3 xl:col-span-4">
                    <label for="token_examination" class="field-label mb-1">Result</label>
                    <select id="token_examination" name="examination_id" onchange="this.form.submit()" class="w-full">
                        <option value="">All results</option>
                        @foreach ($examinationOptions as $id => $label)
                            <option value="{{ $id }}" @selected($tokenFilters['examination_id'] === $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex flex-wrap items-end gap-2 sm:col-span-2 lg:col-span-3 xl:col-span-2 xl:justify-end">
                    <button type="submit" class="btn rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        <i class="fa-solid fa-filter btn-icon" aria-hidden="true"></i> Filter
                    </button>
                    @if ($hasTokenFilter)
                        <a href="{{ route('result-pins.index') }}" class="btn rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Clear
                        </a>
                        @if ($tokens->total() > 0)
                            <button type="button" @click="confirming = 'matching'" class="btn rounded-[8px] border border-red-300 bg-red-50 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300 dark:hover:bg-red-900/40">
                                <i class="fa-solid fa-trash-can btn-icon" aria-hidden="true"></i> Delete all {{ number_format($tokens->total()) }} matching
                            </button>
                        @endif
                    @endif
                </div>
            </form>

            {{-- Shown while anything is ticked. --}}
            <div
                x-show="selected.length"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 -translate-y-1"
                x-transition:enter-end="opacity-100 translate-y-0"
                class="sticky top-0 z-10 flex flex-wrap items-center justify-between gap-3 border-b border-red-200 bg-red-50 px-6 py-3 dark:border-red-900/50 dark:bg-red-900/20"
                role="region"
                aria-label="Selected tokens"
            >
                <p class="text-sm font-semibold text-red-800 dark:text-red-300">
                    <i class="fa-solid fa-square-check mr-1" aria-hidden="true"></i>
                    <span x-text="selected.length"></span>
                    <span x-text="selected.length === 1 ? 'token selected' : 'tokens selected'"></span>
                </p>
                <div class="flex flex-wrap items-center gap-2">
                    {{-- Send the ticked students' result details to their
                         parents and guardians, after a confirmation. --}}
                    <x-send-result-details :action="route('result-pins.details.preview')" label="Send Result Details">
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="tokens[]" :value="id">
                        </template>
                    </x-send-result-details>

                    <button type="button" @click="selected = []" class="btn rounded-[8px] border border-gray-300 bg-white font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                        <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Clear selection
                    </button>
                    <button type="button" @click="confirming = 'selected'" class="btn rounded-[8px] bg-red-600 font-semibold text-white hover:bg-red-700">
                        <i class="fa-solid fa-trash-can btn-icon" aria-hidden="true"></i> <span x-text="'Delete selected (' + selected.length + ')'">Delete selected</span>
                    </button>
                </div>
            </div>

            {{-- The two bulk forms. Both carry the current filters back, so the
                 admin lands on the same view afterwards. --}}
            <form x-ref="deleteSelected" method="POST" action="{{ route('result-pins.bulk-destroy') }}" class="hidden">
                @csrf
                @method('DELETE')
                @foreach (array_merge($tokenFilterQuery, request()->only('page')) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <template x-for="id in selected" :key="id">
                    <input type="hidden" name="tokens[]" :value="id">
                </template>
            </form>

            @if ($hasTokenFilter && $tokens->total() > 0)
                <form x-ref="deleteMatching" method="POST" action="{{ route('result-pins.destroy-matching') }}" class="hidden">
                    @csrf
                    @method('DELETE')
                    @foreach ($tokenFilterQuery as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <input type="hidden" name="expected_count" value="{{ $tokens->total() }}">
                </form>
            @endif

            {{-- One confirmation box for both bulk deletes. --}}
            <div x-show="confirming" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" role="dialog" aria-modal="true" aria-labelledby="token-delete-title">
                <div @click.outside="confirming = null" x-show="confirming" x-transition class="w-full max-w-md rounded-[10px] bg-white p-6 shadow-xl dark:bg-gray-800">
                    <div class="flex items-start gap-3">
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400">
                            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                        </span>
                        <div class="min-w-0">
                            <h3 id="token-delete-title" class="text-base font-bold text-gray-900 dark:text-white">
                                <template x-if="confirming === 'selected'">
                                    <span>Delete <span x-text="selected.length"></span> <span x-text="selected.length === 1 ? 'token' : 'tokens'"></span>?</span>
                                </template>
                                <template x-if="confirming === 'matching'">
                                    <span>Delete all {{ number_format($tokens->total()) }} matching {{ $tokens->total() === 1 ? 'token' : 'tokens' }}?</span>
                                </template>
                            </h3>
                            <small class="mt-1 block text-sm text-gray-600 dark:text-gray-300">
                                This cannot be undone. Parents holding these tokens will no longer be able to open the results, so issue new ones to anyone who still needs them.
                            </small>
                        </div>
                    </div>

                    <ul x-show="confirming === 'selected'" class="mt-4 max-h-48 space-y-1 overflow-y-auto rounded-[8px] bg-gray-50 p-3 text-sm text-gray-800 dark:bg-gray-900/50 dark:text-gray-200">
                        <template x-for="id in selected" :key="id">
                            <li class="flex items-center gap-2"><i class="fa-solid fa-key text-xs text-gray-400" aria-hidden="true"></i><span x-text="names[id]"></span></li>
                        </template>
                    </ul>

                    @if ($hasTokenFilter)
                        <div x-show="confirming === 'matching'" class="mt-4 rounded-[8px] bg-gray-50 p-3 text-sm text-gray-800 dark:bg-gray-900/50 dark:text-gray-200">
                            <p class="font-semibold">Every token on every page matching:</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($tokenFilterSummary as $part)
                                    <li>{{ ucfirst($part) }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="confirming = null" class="btn rounded-[8px] border border-gray-300 font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                            <i class="fa-solid fa-xmark btn-icon" aria-hidden="true"></i> Cancel
                        </button>
                        <button type="button" @click="confirming === 'matching' ? $refs.deleteMatching.submit() : $refs.deleteSelected.submit()" class="btn rounded-[8px] bg-red-600 font-semibold text-white hover:bg-red-700">
                            <i class="fa-solid fa-trash-can btn-icon" aria-hidden="true"></i> Delete
                        </button>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="w-10 py-3 pl-5 pr-0">
                                <input
                                    type="checkbox"
                                    class="h-4 w-4 cursor-pointer rounded border-gray-300 text-red-600 focus:ring-red-500"
                                    :checked="allSelected"
                                    :indeterminate="someSelected"
                                    @change="toggleAll($event.target.checked)"
                                    :disabled="pageIds.length === 0"
                                    aria-label="Select every token on this page"
                                    data-tooltip="Select all on this page"
                                >
                            </th>
                            <th class="px-5 py-3 font-semibold">Student</th>
                            <th class="px-5 py-3 font-semibold">Result</th>
                            <th class="px-5 py-3 font-semibold">Views</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($tokens as $token)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50" :class="selected.includes(@js($token->uuid)) && '!bg-red-50/70 dark:!bg-red-900/15'">
                                <td class="w-10 py-3 pl-5 pr-0">
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 cursor-pointer rounded border-gray-300 text-red-600 focus:ring-red-500"
                                        value="{{ $token->uuid }}"
                                        x-model="selected"
                                        aria-label="Select the token for {{ $token->boundStudent?->fullName() ?? 'this row' }}"
                                    >
                                </td>
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $token->boundStudent?->fullName() ?? 'N/A' }}</p>
                                    <small class="field-hint">{{ $token->boundStudent?->admission_number }}</small>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-300">
                                    @if ($token->examination)
                                        {{ $token->examination->name }}, {{ $token->examination->class_name }}<br>
                                        {{ $token->examination->term->label() }} &middot; {{ $token->examination->session }}
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $token->uses_count }} / {{ $token->max_uses }}</td>
                                <td class="px-5 py-3">
                                    @php($colour = $token->displayStatusColour())
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                        @class([
                                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $colour === 'green',
                                            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' => $colour === 'amber',
                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $colour === 'red',
                                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => $colour === 'gray',
                                        ])">
                                        {{ $token->displayStatusLabel() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="icon-btn-group">
                                        @if ($token->boundStudent && $token->accessRefusalReason() === null)
                                            <x-send-result-details :action="route('result-pins.details.send')" :compact="true" label="Send result details to parents">
                                                <input type="hidden" name="tokens[]" value="{{ $token->uuid }}">
                                            </x-send-result-details>
                                        @endif

                                        <form method="POST" action="{{ route('result-pins.reveal', $token) }}">
                                            @csrf
                                            <button type="submit" class="icon-btn icon-btn--view" data-tooltip="Show token" aria-label="Show token"><i class="fa-solid fa-eye" aria-hidden="true"></i></button>
                                        </form>

                                        <form method="POST" action="{{ route('result-pins.reissue', $token) }}">
                                            @csrf
                                            <button type="submit" class="icon-btn icon-btn--neutral" data-tooltip="Reissue token" aria-label="Reissue token"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></button>
                                        </form>

                                        @if ($token->status->allowsAccess() || $token->status->isReversible())
                                            <form method="POST" action="{{ route('result-pins.suspend', $token) }}">
                                                @csrf
                                                @php($isReversible = $token->status->isReversible())
                                                <button type="submit" class="icon-btn {{ $isReversible ? 'icon-btn--approve' : 'icon-btn--warn' }}" data-tooltip="{{ $isReversible ? 'Restore token' : 'Suspend token' }}" aria-label="{{ $isReversible ? 'Restore token' : 'Suspend token' }}">
                                                    <i class="fa-solid {{ $isReversible ? 'fa-rotate-left' : 'fa-circle-pause' }}" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('result-pins.revoke', $token) }}">
                                            @csrf
                                            <button type="submit" class="icon-btn icon-btn--warn" data-tooltip="Revoke token" aria-label="Revoke token"><i class="fa-solid fa-ban" aria-hidden="true"></i></button>
                                        </form>

                                        <form method="POST" action="{{ route('result-pins.destroy', $token) }}" onsubmit="return confirm(@js('Delete the token for '.($token->boundStudent?->fullName() ?? 'this student').'? This cannot be undone.'));">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="icon-btn icon-btn--delete" data-tooltip="Delete token" aria-label="Delete token"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    @if ($hasTokenFilter)
                                        No tokens match these filters.
                                    @else
                                        No tokens issued yet. Choose a result above to issue them.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($tokens->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">{{ $tokens->links() }}</div>
            @endif
        </div>

        {{-- Who has been trying to read results --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent result access</h2>
                <small class="field-hint mt-0.5">
                    Every attempt, successful or not. Repeated failures from one address are worth a look.
                </small>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">When</th>
                            <th class="px-5 py-3 font-semibold">Student</th>
                            <th class="px-5 py-3 font-semibold">Outcome</th>
                            <th class="px-5 py-3 font-semibold">From</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($recentAccess as $entry)
                            <tr>
                                <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-300">{{ $entry->occurred_at->format('j M Y, g:ia') }}</td>
                                <td class="px-5 py-3 text-gray-900 dark:text-white">{{ $entry->student?->fullName() ?? 'N/A' }}</td>
                                <td class="px-5 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold
                                        @class([
                                            'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' => $entry->outcome->succeeded(),
                                            'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' => $entry->outcome->isSuspicious(),
                                            'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' => ! $entry->outcome->succeeded() && ! $entry->outcome->isSuspicious(),
                                        ])">
                                        {{ $entry->outcome->label() }}
                                    </span>
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $entry->ip_address ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Nobody has tried to open a result yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-dashboard-layout>
