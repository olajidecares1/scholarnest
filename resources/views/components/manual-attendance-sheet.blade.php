{{-- Manual attendance for one class and one term.

     The teacher types the number of days Present, Absent, Late and Excused.
     The total, the attendance percentage and whether the total agrees with
     the term's configured school days are worked out live, a preview of what
     the server recalculates on save and what the report card will show.

     One set of inputs, laid out as a card per student on a phone and as a row
     of a grid from lg up, rather than a table and a separate mobile list: two
     copies of each input would submit twice under the same name.

     Shared by the Teacher Portal and the School Admin panel, so both enter
     the same figures in the same way. --}}
@props([
    'rows',
    'academicTerm',
    'action',
    'className',
    'session',
    'term',
    'classes',
    'filterUrl',
    'registerUrl',

    // The Teacher Portal locks students whose card is already published;
    // the School Admin may still correct them.
    'enforceLocks' => true,
])

@php
    use App\Enums\ExamTerm;
    use App\Models\AttendanceSummary;
    use App\Support\AcademicSession;

    $schoolDays = $academicTerm?->school_days;
    $fieldLabels = [
        'days_present' => ['Present', 'text-green-600 dark:text-green-400'],
        'days_absent' => ['Absent', 'text-red-600 dark:text-red-400'],
        'days_late' => ['Late', 'text-amber-600 dark:text-amber-400'],
        'days_excused' => ['Excused', 'text-blue-600 dark:text-blue-400'],
    ];
    $flaggedCount = $rows->filter(fn ($row) => $row['saved'] && AttendanceSummary::discrepancyBetween(array_sum($row['values']), $schoolDays))->count();
    $savedCount = $rows->where('saved', true)->count();
@endphp

<div class="space-y-6">
    <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 sm:p-6 lg:rounded-[10px]">
        <form method="GET" action="{{ $filterUrl }}" class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:items-end">
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Class</span>
                <select name="class" onchange="this.form.submit()" class="w-full">
                    @foreach ($classes as $class)
                        <option value="{{ $class }}" @selected($className === $class)>{{ $class }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Session</span>
                <select name="session" onchange="this.form.submit()" class="w-full">
                    @foreach (AcademicSession::options() as $option)
                        <option value="{{ $option }}" @selected($session === $option)>{{ $option }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Term</span>
                <select name="term" onchange="this.form.submit()" class="w-full">
                    @foreach (ExamTerm::cases() as $option)
                        <option value="{{ $option->value }}" @selected($term === $option)>{{ $option->label() }}</option>
                    @endforeach
                </select>
            </label>
        </form>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <p class="text-sm font-medium text-purple-600">School days this term</p>
            <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ $schoolDays ?? '—' }}</p>
            @if ($schoolDays === null)
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Not configured. Your School Admin sets it under Academics &rarr; Term Dates; until then totals are not checked.</p>
            @endif
        </div>
        <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <p class="text-sm font-medium text-blue-600">Students recorded</p>
            <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ $savedCount }} / {{ $rows->count() }}</p>
        </div>
        <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <p class="text-sm font-medium text-amber-600">Flagged discrepancies</p>
            <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ $flaggedCount }}</p>
        </div>
    </div>

    <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
        <div class="flex flex-col gap-3 border-b border-gray-100 p-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between sm:p-6">
            <div>
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $className }} &middot; {{ $term->label() }} {{ $session }}</h2>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Enter the number of days for each status. Prefer marking day by day? Use the <a href="{{ $registerUrl }}" class="font-semibold text-blue-600 hover:underline">daily register</a>, then fill these totals from it.</p>
            </div>
            @if ($academicTerm)
                <a href="{{ $filterUrl }}?{{ http_build_query(['class' => $className, 'session' => $session, 'term' => $term->value, 'prefill' => 1]) }}" class="inline-flex items-center justify-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                    <i class="fa-solid fa-wand-magic-sparkles text-xs"></i> Fill blanks from daily register
                </a>
            @endif
        </div>

        <form method="POST" action="{{ $action }}">
            @csrf
            <input type="hidden" name="class_name" value="{{ $className }}">
            <input type="hidden" name="session" value="{{ $session }}">
            <input type="hidden" name="term" value="{{ $term->value }}">

            @if ($errors->any())
                <div class="m-4 rounded-[5px] bg-red-50 p-3 text-sm text-red-700 dark:bg-red-900/30 dark:text-red-400">
                    Each figure must be a whole number of days between 0 and 366.
                </div>
            @endif

            {{-- Column headings, shown only where the rows sit side by side. --}}
            <div class="hidden grid-cols-12 gap-3 bg-gray-50 px-6 py-3 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400 lg:grid">
                <span class="col-span-3">Student</span>
                @foreach ($fieldLabels as [$label])
                    <span class="col-span-1 text-center">{{ $label }}</span>
                @endforeach
                <span class="col-span-2 text-center">Total &middot; %</span>
                <span class="col-span-3">Check</span>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-700">
                @forelse ($rows as $row)
                    @php
                        $student = $row['student'];
                        $isLocked = $enforceLocks && $row['locked'];
                    @endphp
                    <div
                        class="grid grid-cols-1 gap-3 p-4 lg:grid-cols-12 lg:items-center lg:px-6 lg:py-3"
                        x-data="{
                            days: @js($row['values']),
                            schoolDays: @js($schoolDays),
                            get total() { return Object.values(this.days).reduce((sum, value) => sum + (parseInt(value) || 0), 0) },
                            get percent() { return this.total ? Math.round(((parseInt(this.days.days_present) || 0) + (parseInt(this.days.days_late) || 0)) / this.total * 100) : null },
                            get discrepancy() {
                                if (this.schoolDays === null) return null
                                if (this.total > this.schoolDays) return 'over'
                                if (this.total < this.schoolDays) return 'short'
                                return null
                            },
                            allPresent() { this.days = { days_present: this.schoolDays, days_absent: 0, days_late: 0, days_excused: 0 } },
                        }"
                    >
                        <div class="flex items-center gap-3 lg:col-span-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    @if ($row['locked'])
                                        <span class="font-semibold text-gray-600 dark:text-gray-300"><i class="fa-solid fa-lock text-[10px]"></i> Card published{{ $isLocked ? ', locked' : '' }}</span>
                                    @elseif ($row['saved'])
                                        Saved {{ $row['updatedAt']?->diffForHumans() }}
                                    @elseif ($row['fromRegister'])
                                        <span class="text-blue-600 dark:text-blue-400">From daily register, not saved yet</span>
                                    @else
                                        Not recorded
                                    @endif
                                </p>
                            </div>
                        </div>

                        <div class="grid grid-cols-4 gap-2 lg:col-span-4 lg:gap-3">
                            @foreach ($fieldLabels as $field => [$label, $labelColour])
                                <label class="block">
                                    <span class="mb-1 block text-center text-[11px] font-semibold {{ $labelColour }} lg:sr-only">{{ $label }}</span>
                                    <input
                                        type="number"
                                        inputmode="numeric"
                                        min="0"
                                        max="366"
                                        name="entries[{{ $student->id }}][{{ $field }}]"
                                        x-model.number="days.{{ $field }}"
                                        @disabled($isLocked)
                                        aria-label="{{ $label }} days for {{ $student->fullName() }}"
                                        class="w-full rounded-[6px] border-gray-300 px-1 py-2 text-center text-sm disabled:cursor-not-allowed disabled:bg-gray-100 dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:disabled:bg-gray-900"
                                    >
                                </label>
                            @endforeach
                        </div>

                        <div class="flex items-center justify-between gap-3 text-sm lg:col-span-2 lg:justify-center">
                            <span class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400 lg:hidden">Total &middot; %</span>
                            <span class="font-bold text-gray-900 dark:text-white"><span x-text="total"></span> days &middot; <span x-text="percent === null ? '—' : percent + '%'"></span></span>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 lg:col-span-3">
                            <template x-if="schoolDays !== null && discrepancy === null">
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/30 dark:text-green-400"><i class="fa-solid fa-check text-[10px]"></i> Matches school days</span>
                            </template>
                            <template x-if="discrepancy === 'over'">
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-400"><i class="fa-solid fa-triangle-exclamation text-[10px]"></i> <span x-text="(total - schoolDays) + ' over school days'"></span></span>
                            </template>
                            <template x-if="discrepancy === 'short'">
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"><i class="fa-solid fa-triangle-exclamation text-[10px]"></i> <span x-text="(schoolDays - total) + ' short of school days'"></span></span>
                            </template>
                            @if (! $isLocked && $schoolDays !== null)
                                <button type="button" x-on:click="allPresent()" class="rounded-[6px] border border-gray-300 px-2.5 py-1 text-xs font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700">All present</button>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in {{ $className ?: 'this class' }} yet.</p>
                @endforelse
            </div>

            @if ($rows->isNotEmpty())
                <div class="sticky bottom-0 flex justify-end border-t border-gray-100 bg-white p-4 dark:border-gray-700 dark:bg-gray-800 sm:p-6">
                    <button type="submit" class="w-full rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md sm:w-auto">
                        Save Attendance Totals
                    </button>
                </div>
            @endif
        </form>
    </div>
</div>
