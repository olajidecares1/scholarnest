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
                <p class="field-hint mt-0.5">
                    Share this with parents alongside each student's token. It only ever opens results for this school &mdash;
                    a token from another school will not work here.
                </p>
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
                        class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <span x-show="! copied">Copy link</span>
                        <span x-show="copied" x-cloak class="text-green-600 dark:text-green-400">Copied</span>
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
                        <button type="submit" class="rounded-[8px] bg-amber-100 px-3 py-1.5 text-xs font-semibold text-amber-700 transition hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300">
                            {{ $school->resultLinkIsLive() ? 'Switch off' : 'Switch back on' }}
                        </button>
                    </form>

                    <form
                        method="POST"
                        action="{{ route('result-pins.link.regenerate') }}"
                        onsubmit="return confirm('Generate a new link? The current one stops working immediately and can never be reused, so every parent will need the new address. Tokens already issued keep working.')"
                    >
                        @csrf
                        <button type="submit" class="rounded-[8px] bg-red-100 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300">
                            Generate a new link
                        </button>
                    </form>

                    <p class="text-xs text-gray-400 dark:text-gray-500">
                        Switching off is reversible. Generating a new link is not &mdash; the old address is retired permanently.
                    </p>
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
                    <button type="button" onclick="window.print()" class="rounded-[8px] bg-green-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-green-800 print:hidden">
                        Print list
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
                                        <p class="field-hint">{{ $issued['admission_number'] }}</p>
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
                <p class="field-hint mt-0.5">
                    For a single student and a single result.
                </p>

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

                <button type="submit" class="mt-4 w-full rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Issue token
                </button>
            </form>

            {{-- A whole class --}}
            <form method="POST" action="{{ route('result-pins.store-bulk') }}" class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                @csrf
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Issue for a whole class</h2>
                <p class="field-hint mt-0.5">
                    One token per student in the examination's class. Students who already hold a
                    token for this result are skipped, so running it twice is safe.
                </p>

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
                        <p class="field-hint mt-1" x-show="className !== ''" x-cloak>
                            <span x-text="studentCount"></span> active student<span x-show="studentCount !== 1">s</span>
                            in this class &mdash; one token will be generated for each.
                        </p>
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

                <button type="submit" class="mt-4 w-full rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    Generate tokens for this class
                </button>
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
                    <p class="field-hint mt-0.5">
                        Who has used their token, who has not, and when.
                    </p>
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
                            <p class="field-hint">{{ $label }}</p>
                            <p class="mt-1 text-2xl font-extrabold {{ $tone }}">{{ number_format($value) }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[46rem] text-left text-sm">
                        <thead class="border-y border-gray-100 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
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
                                    <td class="px-6 py-3">
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $row['student']->fullName() }}</span>
                                        <span class="block text-xs text-gray-400">{{ $row['student']->admission_number }}</span>
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
                <p class="field-hint p-6">Choose a class result above to see who has used their token.</p>
            @endif
        </div>

        {{-- Issued tokens --}}
        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <div>
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Issued tokens</h2>
                    <p class="field-hint mt-0.5">
                        Each row is one student and one result. A token never opens anything else.
                    </p>
                </div>

                <form method="GET" class="flex flex-wrap items-center gap-2">
                    <select name="examination_id" onchange="this.form.submit()" >
                        <option value="">All results</option>
                        @foreach ($examinationOptions as $id => $label)
                            <option value="{{ $id }}" @selected(request('examination_id') == $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <select name="status" onchange="this.form.submit()" >
                        <option value="">All statuses</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(request('status') === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Student</th>
                            <th class="px-5 py-3 font-semibold">Result</th>
                            <th class="px-5 py-3 font-semibold">Views</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($tokens as $token)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-5 py-3">
                                    <p class="font-semibold text-gray-900 dark:text-white">{{ $token->boundStudent?->fullName() ?? 'N/A' }}</p>
                                    <p class="field-hint">{{ $token->boundStudent?->admission_number }}</p>
                                </td>
                                <td class="px-5 py-3 text-xs text-gray-600 dark:text-gray-300">
                                    @if ($token->examination)
                                        {{ $token->examination->name }}<br>
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
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <form method="POST" action="{{ route('result-pins.reveal', $token) }}">
                                            @csrf
                                            <button type="submit" class="rounded-[6px] bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200">Show token</button>
                                        </form>

                                        <form method="POST" action="{{ route('result-pins.reissue', $token) }}">
                                            @csrf
                                            <button type="submit" class="rounded-[6px] bg-blue-100 px-2 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-200 dark:bg-blue-900/40 dark:text-blue-300">Reissue</button>
                                        </form>

                                        @if ($token->status->allowsAccess() || $token->status->isReversible())
                                            <form method="POST" action="{{ route('result-pins.suspend', $token) }}">
                                                @csrf
                                                <button type="submit" class="rounded-[6px] bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-700 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300">
                                                    {{ $token->status->isReversible() ? 'Restore' : 'Suspend' }}
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="{{ route('result-pins.revoke', $token) }}">
                                            @csrf
                                            <button type="submit" class="rounded-[6px] bg-red-100 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-200 dark:bg-red-900/40 dark:text-red-300">Revoke</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No tokens issued yet. Choose a result above to issue them.
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
                <p class="field-hint mt-0.5">
                    Every attempt, successful or not. Repeated failures from one address are worth a look.
                </p>
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
