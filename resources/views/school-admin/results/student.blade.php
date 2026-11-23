@php
    $ordinal = fn (?int $n) => $n ? $n.match (true) {
        in_array($n % 100, [11, 12, 13]) => 'th',
        $n % 10 === 1 => 'st',
        $n % 10 === 2 => 'nd',
        $n % 10 === 3 => 'rd',
        default => 'th',
    } : 'N/A';
    $percent = fn (?float $value) => $value !== null ? \App\Support\Mark::format($value, 1, true).'%' : 'N/A';
@endphp

<x-dashboard-layout page-title="{{ $student->fullName() }}" page-subtitle="All saved results for admission number {{ $student->admission_number }}, every term and every session.">
    <div class="space-y-6">
        <div class="flex flex-wrap justify-end gap-2">
            <a
                href="{{ route('results.archive') }}"
                class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-chevron-left text-[12px]"></i>
                All Saved Results
            </a>
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Admission No.</small>
                <span class="mt-1 block font-mono text-sm font-bold text-gray-900 dark:text-white">{{ $student->admission_number }}</span>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Class now</small>
                <span class="mt-1 block text-sm font-bold text-gray-900 dark:text-white">{{ $student->class_name ?? 'N/A' }}</span>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Sessions</small>
                <span class="mt-1 block text-sm font-bold text-gray-900 dark:text-white">{{ $history->count() }}</span>
            </div>
            <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500">Terms with results</small>
                <span class="mt-1 block text-sm font-bold text-gray-900 dark:text-white">{{ $history->flatten(1)->count() }}</span>
            </div>
        </div>

        @unless ($student->is_active)
            <div class="rounded-[10px] bg-amber-50 p-4 text-sm font-medium text-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                This student is no longer active. Their saved results are kept and shown below.
            </div>
        @endunless

        @if ($history->isEmpty())
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No results saved for this student yet</p>
            </div>
        @endif

        @foreach ($history as $session => $terms)
            @php $sessionResult = $cumulative->get($session); @endphp

            <div class="rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">{{ $session }} Academic Session</h2>
                    <small class="text-xs text-gray-500">{{ collect($terms)->pluck('class_name')->unique()->join(', ') }}</small>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="px-5 py-2.5 font-semibold">Term</th>
                                <th class="px-3 py-2.5 font-semibold">Class</th>
                                <th class="px-3 py-2.5 text-center font-semibold">Subjects</th>
                                <th class="px-3 py-2.5 text-center font-semibold">Total</th>
                                <th class="px-3 py-2.5 text-center font-semibold">Average</th>
                                <th class="px-3 py-2.5 text-center font-semibold">Position</th>
                                <th class="px-5 py-2.5 text-right font-semibold">Report Card</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($terms as $row)
                                <tr>
                                    <td class="px-5 py-2.5 font-semibold text-gray-900 dark:text-white">{{ $row['term']->label() }}</td>
                                    <td class="px-3 py-2.5 text-gray-700 dark:text-gray-200">
                                        <a href="{{ route('results.index', ['class' => $row['class_name'], 'session' => $session, 'term' => $row['term']->value]) }}" class="hover:text-primary-600 hover:underline" data-tooltip="The whole class's results">{{ $row['class_name'] }}</a>
                                    </td>
                                    <td class="px-3 py-2.5 text-center text-gray-700 dark:text-gray-200">{{ $row['subjects'] }}</td>
                                    <td class="px-3 py-2.5 text-center text-gray-700 dark:text-gray-200">{{ \App\Support\Mark::format($row['total'], 2, true) }} / {{ $row['max'] }}</td>
                                    <td class="px-3 py-2.5 text-center font-bold text-gray-900 dark:text-white">{{ $percent($row['average']) }}</td>
                                    <td class="px-3 py-2.5 text-center text-gray-700 dark:text-gray-200">
                                        {{ $ordinal($row['position']) }}@if ($row['position']) <small class="text-xs text-gray-500">of {{ $row['number_in_class'] }}</small>@endif
                                    </td>
                                    <td class="px-5 py-2.5 text-right">
                                        <div class="inline-flex gap-1.5">
                                            <a href="{{ route('results.print', [$row['examination'], $student]) }}" target="_blank" class="icon-btn icon-btn--neutral" data-tooltip="View &amp; print" aria-label="View and print the {{ $row['term']->label() }} report card">
                                                <i class="fa-solid fa-print" aria-hidden="true"></i>
                                            </a>
                                            <a href="{{ route('results.pdf', [$row['examination'], $student]) }}" class="icon-btn icon-btn--download" data-tooltip="Download PDF" aria-label="Download the {{ $row['term']->label() }} report card">
                                                <i class="fa-solid fa-file-arrow-down" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- The session's cumulative, from the same saved results,
                     matched on the admission number. --}}
                @if ($sessionResult)
                    @php $covered = collect($termOptions)->take(array_search(\App\Enums\ExamTerm::from($sessionResult['upTo']), $termOptions, true) + 1); @endphp
                    <div class="border-t border-gray-100 bg-primary-50/40 px-5 py-4 dark:border-gray-700 dark:bg-primary-900/10">
                        <div class="flex flex-wrap items-end justify-between gap-4">
                            <div>
                                <h3 class="text-xs font-bold uppercase tracking-wide text-primary-700 dark:text-primary-300">
                                    Cumulative &middot; {{ $covered->map->label()->join(' + ') }}
                                </h3>
                                <small class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">
                                    Fetched automatically by admission number {{ $student->admission_number }}, ranked within {{ $sessionResult['className'] }}.
                                </small>
                            </div>
                            <div class="flex flex-wrap gap-6 text-center">
                                <div>
                                    <small class="block text-[11px] font-semibold uppercase text-gray-500">Total</small>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ \App\Support\Mark::format($sessionResult['total'], 2, true) }} / {{ $sessionResult['max'] }}</span>
                                </div>
                                <div>
                                    <small class="block text-[11px] font-semibold uppercase text-gray-500">Average</small>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $percent($sessionResult['average']) }}</span>
                                </div>
                                <div>
                                    <small class="block text-[11px] font-semibold uppercase text-gray-500">Grade</small>
                                    <span class="text-sm font-extrabold text-gray-900 dark:text-white">{{ $sessionResult['grade'] }}</span>
                                </div>
                                <div>
                                    <small class="block text-[11px] font-semibold uppercase text-gray-500">Position</small>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white">{{ $ordinal($sessionResult['position']) }} <small class="font-medium text-gray-500">of {{ $sessionResult['numberInClass'] }}</small></span>
                                </div>
                            </div>
                        </div>

                        @if (! empty($sessionResult['subjects']))
                            <div class="mt-4 overflow-x-auto">
                                <table class="w-full min-w-[36rem] text-left text-xs">
                                    <thead class="text-[11px] uppercase text-gray-500">
                                        <tr>
                                            <th class="py-1.5 pr-3 font-semibold">Subject</th>
                                            @foreach ($covered as $term)
                                                <th class="px-2 py-1.5 text-center font-semibold">{{ $term->label() }}</th>
                                            @endforeach
                                            <th class="px-2 py-1.5 text-center font-semibold">Average</th>
                                            <th class="px-2 py-1.5 text-center font-semibold">Grade</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-primary-100 dark:divide-gray-700">
                                        @foreach ($sessionResult['subjects'] as $subject)
                                            <tr>
                                                <td class="py-1.5 pr-3 font-semibold text-gray-800 dark:text-gray-100">{{ $subject['name'] }}</td>
                                                @foreach ($covered as $term)
                                                    <td class="px-2 py-1.5 text-center text-gray-700 dark:text-gray-200">{{ $percent($subject['terms'][$term->value] ?? null) }}</td>
                                                @endforeach
                                                <td class="px-2 py-1.5 text-center font-bold text-gray-900 dark:text-white">{{ $percent($subject['average']) }}</td>
                                                <td class="px-2 py-1.5 text-center font-bold text-gray-900 dark:text-white">{{ $subject['grade'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @elseif (! $cumulativeEnabled && $loop->first)
                    <div class="border-t border-gray-100 px-5 py-3 text-xs text-gray-500 dark:border-gray-700">
                        Cumulative results are off for your school. Turn them on under
                        <a href="{{ route('academics.index') }}#cumulative-results" class="font-semibold text-primary-600 underline dark:text-primary-400">Academics &rsaquo; Cumulative Session Results</a>
                        to see each session's terms added together here.
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</x-dashboard-layout>
