{{-- Manual term attendance: one class, one term, a row per pupil with the
     four day counts, the running total and how it compares with the term.

     Shared by the Teacher Portal and the School Admin so both enter, total
     and flag the figures identically. The page around it owns the session,
     term and class pickers; this owns the form.

     Works without JavaScript (and therefore from the offline cache): every
     count is an ordinary number field and the form posts as it stands. Alpine
     only adds the live total, the discrepancy badge and the quick fills.

     Sized by the width of the form, not the screen (container queries), so
     the narrow Teacher Portal column and the wide School Admin page both get
     the right layout: stacked with two columns of counts on a phone, four
     columns of counts from tablet width, one line per pupil when wide. --}}
@props([
    'action',
    'rows',
    'hidden' => [],
    'expectedDays' => null,
    'academicTerm' => null,
    'canOverwrite' => true,
    'showClass' => false,
    'termLabel' => '',
    'termDatesUrl' => null,
])

@php
    $countFields = [
        'days_present' => ['Present', 'text-green-700 dark:text-green-400'],
        'days_absent' => ['Absent', 'text-red-700 dark:text-red-400'],
        'days_late' => ['Late', 'text-amber-700 dark:text-amber-400'],
        'days_excused' => ['Excused', 'text-blue-700 dark:text-blue-400'],
    ];

    $entered = $rows->filter(fn ($row) => $row['summary'] !== null)->count();
    $flagged = $rows->filter(fn ($row) => $row['discrepancy'] !== null)->count();
    $hasRegister = $rows->contains(fn ($row) => $row['register'] !== null);
@endphp

<div class="space-y-4">
    {{-- The term, and what the totals are checked against. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <p class="text-xs font-medium text-purple-600">Students</p>
            <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">{{ number_format($rows->count()) }}</p>
        </div>
        <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <p class="text-xs font-medium text-blue-600">Totals entered</p>
            <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">{{ number_format($entered) }}</p>
        </div>
        <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <p class="text-xs font-medium text-green-600">School days</p>
            <p class="mt-1 text-2xl font-extrabold text-gray-900 dark:text-white">{{ $expectedDays ?? '—' }}</p>
        </div>
        <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <p class="text-xs font-medium {{ $flagged ? 'text-amber-600' : 'text-gray-500 dark:text-gray-400' }}">Need checking</p>
            <p class="mt-1 text-2xl font-extrabold {{ $flagged ? 'text-amber-600' : 'text-gray-900 dark:text-white' }}">{{ number_format($flagged) }}</p>
        </div>
    </div>

    @if ($academicTerm)
        <small class="block text-xs leading-[1.6] text-gray-500 dark:text-gray-400">
            {{ $termLabel }} runs {{ $academicTerm->starts_on->format('M j, Y') }} to {{ $academicTerm->ends_on->format('M j, Y') }}.
            @if ($academicTerm->hasConfiguredSchoolDays())
                The school set {{ $expectedDays }} school days for it.
            @else
                {{ $expectedDays }} school days are worked out from those dates (Monday to Friday). The School Admin can set the exact number under Academics &rarr; Terms.
            @endif
            Each student's present, absent, late and excused days should add up to that number; any that don't are flagged.
        </small>
    @else
        <div class="flex items-start gap-2.5 rounded-[8px] border border-amber-200 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-900/20">
            <i class="fa-solid fa-triangle-exclamation mt-0.5 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true"></i>
            <p class="text-xs leading-[1.6] text-amber-900 dark:text-amber-300">
                {{ $termLabel }} has no dates or school days set yet, so totals can be saved but not checked against the term.
                @if ($termDatesUrl)
                    <a href="{{ $termDatesUrl }}" class="font-semibold underline">Set them under Academics &rarr; Terms.</a>
                @else
                    Ask your School Admin to set them under Academics &rarr; Terms.
                @endif
            </p>
        </div>
    @endif

    @unless ($canOverwrite)
        <div class="flex items-start gap-2.5 rounded-[8px] border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-900/40">
            <i class="fa-solid fa-lock mt-0.5 shrink-0 text-gray-500" aria-hidden="true"></i>
            <p class="text-xs leading-[1.6] text-gray-700 dark:text-gray-300">
                At this school, totals can only be corrected by the School Admin once saved. Students without totals can still be filled in.
            </p>
        </div>
    @endunless

    <form method="POST" action="{{ $action }}" class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
        @csrf
        @foreach ($hidden as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        @if ($rows->isNotEmpty())
            {{-- Quick fills for the whole class. They only touch rows that are
                 still blank, so nothing already typed is overwritten. --}}
            <div class="flex flex-col gap-2 border-b border-gray-100 p-4 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between lg:px-6 dark:border-gray-700">
                <small class="block text-xs text-gray-500 dark:text-gray-400">
                    Type the number of days, or pick a status per student to fill every school day with it.
                </small>
                <div class="flex flex-col gap-2 sm:flex-row">
                    @if ($expectedDays)
                        <button type="button" x-data x-on:click="$dispatch('term-attendance-fill', { mode: 'days_present' })"
                            class="btn rounded-[8px] border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            <i class="fa-solid fa-user-check btn-icon" aria-hidden="true"></i> Blank rows: present all {{ $expectedDays }} days
                        </button>
                    @endif
                    @if ($hasRegister)
                        <button type="button" x-data x-on:click="$dispatch('term-attendance-fill', { mode: 'register' })"
                            class="btn rounded-[8px] border border-gray-300 bg-white px-3 py-2 text-xs font-semibold text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            <i class="fa-solid fa-calendar-check btn-icon" aria-hidden="true"></i> Blank rows: copy the daily register
                        </button>
                    @endif
                </div>
            </div>
        @endif

        <div class="@container divide-y divide-gray-100 dark:divide-gray-700">
            @forelse ($rows as $row)
                @php
                    $student = $row['student'];
                    $summary = $row['summary'];
                    $locked = $summary !== null && ! $canOverwrite;
                    $values = collect($countFields)->keys()->mapWithKeys(fn ($key) => [
                        $key => old("rows.{$student->id}.{$key}", $summary?->{$key}),
                    ]);
                    $savedBy = $summary?->markedByStaff?->fullName() ?? $summary?->markedBy?->name;
                @endphp

                <div
                    class="grid grid-cols-1 gap-3 p-4 @md:px-6 @5xl:grid-cols-[minmax(0,13rem)_minmax(0,1fr)_minmax(0,12rem)] @5xl:items-center @5xl:gap-5"
                    x-data="{
                        c: @js($values->map(fn ($v) => $v === null ? '' : (string) $v)->all()),
                        expected: @js($expectedDays),
                        register: @js($row['register']),
                        locked: @js($locked),
                        filled() { return Object.values(this.c).some(v => v !== '' && v !== null) },
                        total() { return Object.values(this.c).reduce((sum, v) => sum + (parseInt(v, 10) || 0), 0) },
                        attended() { return (parseInt(this.c.days_present, 10) || 0) + (parseInt(this.c.days_late, 10) || 0) },
                        percent() { return this.total() ? Math.round(this.attended() / this.total() * 100) : null },
                        diff() { return this.expected === null || ! this.filled() ? 0 : this.total() - this.expected },
                        fill(mode) {
                            if (this.locked) return;
                            if (mode === 'clear') { for (const k in this.c) this.c[k] = ''; return; }
                            if (mode === 'register') { if (! this.register) return; for (const k in this.c) this.c[k] = String(this.register[k] ?? 0); return; }
                            if (this.expected === null) return;
                            for (const k in this.c) this.c[k] = k === mode ? String(this.expected) : '0';
                        },
                    }"
                    x-on:term-attendance-fill.window="if (! filled()) fill($event.detail.mode)"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                            {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                            @if ($student->photoUrl())
                                <img src="{{ $student->photoUrl() }}" alt="" loading="lazy" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                            @endif
                        </span>
                        <div class="min-w-0">
                            <p class="truncate font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</p>
                            <small class="block truncate text-xs text-gray-500 dark:text-gray-400">
                                @if ($showClass){{ $student->class_name }} &middot; @endif
                                @if ($summary)
                                    Saved {{ $summary->updated_at?->format('M j') }}@if ($savedBy) by {{ $savedBy }}@endif
                                @else
                                    Not entered yet
                                @endif
                            </small>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="grid grid-cols-2 gap-2 @md:grid-cols-4">
                            @foreach ($countFields as $key => [$label, $labelClass])
                                <label class="block">
                                    <span class="mb-1 block truncate text-[11px] font-semibold uppercase tracking-wide {{ $labelClass }}">{{ $label }}</span>
                                    <input
                                        type="number"
                                        name="rows[{{ $student->id }}][{{ $key }}]"
                                        value="{{ $values[$key] }}"
                                        x-model="c.{{ $key }}"
                                        min="0"
                                        max="366"
                                        step="1"
                                        inputmode="numeric"
                                        placeholder="0"
                                        aria-label="{{ $label }} days for {{ $student->fullName() }}"
                                        class="w-full text-center"
                                        @disabled($locked)
                                    >
                                </label>
                            @endforeach
                        </div>
                        <input
                            type="text"
                            name="rows[{{ $student->id }}][notes]"
                            value="{{ old("rows.{$student->id}.notes", $summary?->notes) }}"
                            maxlength="255"
                            placeholder="Note (optional), e.g. joined mid-term"
                            aria-label="Note for {{ $student->fullName() }}"
                            class="w-full text-sm"
                            @disabled($locked)
                        >
                        @error("rows.{$student->id}.*")
                            <p class="text-xs font-semibold text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-row items-center justify-between gap-3 @5xl:flex-col @5xl:items-end">
                        <div class="text-left @5xl:text-right">
                            {{-- Server-rendered first, so the summary is right
                                 before Alpine boots and without it. --}}
                            <p class="text-sm font-bold text-gray-900 dark:text-white">
                                <span x-text="total()">{{ $summary?->totalDays() ?? 0 }}</span>@if ($expectedDays) <span class="font-normal text-gray-500 dark:text-gray-400">/ {{ $expectedDays }} days</span>@else <span class="font-normal text-gray-500 dark:text-gray-400">days</span>@endif
                                <span class="font-normal text-gray-500 dark:text-gray-400">&middot;</span>
                                <span x-text="percent() === null ? 'N/A' : percent() + '%'">{{ $summary?->percent() !== null ? $summary->percent().'%' : 'N/A' }}</span>
                            </p>

                            <div class="mt-1 text-xs font-semibold">
                                <template x-if="! filled()"><span class="text-gray-500 dark:text-gray-400">Not entered</span></template>
                                <template x-if="filled() && expected !== null && diff() === 0"><span class="text-green-700 dark:text-green-400"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> Matches the term</span></template>
                                <template x-if="filled() && diff() > 0"><span class="text-red-700 dark:text-red-400"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <span x-text="diff() + (diff() === 1 ? ' day' : ' days') + ' over'"></span></span></template>
                                <template x-if="filled() && diff() < 0"><span class="text-amber-700 dark:text-amber-400"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i> <span x-text="(-diff()) + (diff() === -1 ? ' day' : ' days') + ' short'"></span></span></template>

                                {{-- What the page said when it was served, for
                                     anyone reading it without JavaScript. --}}
                                <noscript>
                                    @if ($row['discrepancy'])
                                        <span class="{{ $row['discrepancy']['kind'] === 'over' ? 'text-red-700' : 'text-amber-700' }}">{{ ucfirst($row['discrepancy']['message']) }}</span>
                                    @elseif ($summary)
                                        <span class="text-green-700">Saved</span>
                                    @endif
                                </noscript>
                            </div>
                        </div>

                        @if ($locked)
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-500 dark:text-gray-400"><i class="fa-solid fa-lock" aria-hidden="true"></i> School Admin corrects</span>
                        @else
                            <select
                                x-on:change="fill($event.target.value); $event.target.value = ''"
                                aria-label="Quick fill for {{ $student->fullName() }}"
                                class="w-44 text-xs"
                            >
                                <option value="">Fill with a status&hellip;</option>
                                @if ($expectedDays)
                                    <option value="days_present">Present every day</option>
                                    <option value="days_absent">Absent every day</option>
                                    <option value="days_late">Late every day</option>
                                    <option value="days_excused">Excused every day</option>
                                @endif
                                @if ($row['register'])
                                    <option value="register">Copy the daily register ({{ array_sum($row['register']) }} days)</option>
                                @endif
                                <option value="clear">Clear</option>
                            </select>
                        @endif
                    </div>
                </div>
            @empty
                <small class="block px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in this class yet.</small>
            @endforelse
        </div>

        @if ($rows->isNotEmpty())
            <div class="flex flex-col gap-2 border-t border-gray-100 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-6 dark:border-gray-700">
                <small class="text-xs text-gray-500 dark:text-gray-400">Rows left blank are skipped. A blank count beside filled ones is saved as 0.</small>
                <button type="submit" class="btn w-full rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md sm:w-auto">
                    <i class="fa-solid fa-floppy-disk btn-icon" aria-hidden="true"></i> Save {{ $termLabel }} Attendance
                </button>
            </div>
        @endif
    </form>
</div>
