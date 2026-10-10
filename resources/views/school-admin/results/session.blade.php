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

<x-dashboard-layout page-title="Session Results" page-subtitle="First, Second and Third Term added into one result for the whole session.">
    <div class="space-y-6">
        <div class="flex flex-wrap justify-end gap-2">
            <a
                href="{{ route('results.index', ['class' => $selectedClass, 'session' => $selectedSession]) }}"
                class="btn inline-flex items-center gap-2 rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-900 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-100 dark:hover:bg-gray-700"
            >
                <i class="fa-solid fa-chevron-left text-[12px]"></i>
                Back to Term Results
            </a>
        </div>

        @if (! $enabled)
            {{-- Off is a choice, not a fault: said plainly, with where to change it. --}}
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Cumulative session results are off for your school</p>
                <small class="mt-1 block text-sm text-gray-500 dark:text-gray-400">
                    Report cards show each term on its own. To add the three terms together, turn it on under
                    <a href="{{ route('academics.index') }}#cumulative-results" class="font-semibold text-blue-600 underline dark:text-blue-400">Academics &rsaquo; Cumulative Session Results</a>.
                </small>
            </div>
        @else
            <div class="rounded-[5px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <form method="GET" class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div>
                        <label class="field-label" for="session-class">Class</label>
                        <select id="session-class" name="class" onchange="this.form.submit()" class="mt-1 w-full">
                            @foreach ($classOptions as $className)
                                <option value="{{ $className }}" @selected($selectedClass === $className)>{{ $className }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="field-label" for="session-session">Academic Session</label>
                        <select id="session-session" name="session" onchange="this.form.submit()" class="mt-1 w-full">
                            @foreach ($sessionOptions as $sessionOption)
                                <option value="{{ $sessionOption }}" @selected($selectedSession === $sessionOption)>{{ $sessionOption }}</option>
                            @endforeach
                        </select>
                    </div>
                </form>
                <small class="field-hint mt-3">
                    Each term column is that term's average, as printed on its report card. The session average is
                    {{ $basis === \App\Enums\CumulativeAverageBasis::AllTerms ? 'always divided by three terms' : 'the average of the terms each student has results for' }},
                    and graded on your school's grading scale.
                </small>
            </div>

            @if ($rows->isEmpty())
                <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">No students in {{ $selectedClass ?? 'this class' }}</p>
                </div>
            @else
                <div class="overflow-x-auto rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <table class="w-full min-w-[46rem] text-left text-sm">
                        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Student</th>
                                @foreach ($termOptions as $term)
                                    <th class="px-3 py-3 text-center font-semibold">{{ $term->label() }}</th>
                                @endforeach
                                <th class="px-3 py-3 text-center font-semibold">Session Total</th>
                                <th class="px-3 py-3 text-center font-semibold">Session Average</th>
                                <th class="px-3 py-3 text-center font-semibold">Grade</th>
                                <th class="px-3 py-3 text-center font-semibold">Position</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-3">
                                        <span class="font-semibold text-gray-900 dark:text-white">{{ $row['student']->fullName() }}</span>
                                        <small class="block text-xs text-gray-500 dark:text-gray-400">{{ $row['student']->admission_number }}</small>
                                    </td>
                                    @foreach ($termOptions as $term)
                                        <td class="px-3 py-3 text-center text-gray-700 dark:text-gray-200">{{ $percent($row['terms'][$term->value]['average'] ?? null) }}</td>
                                    @endforeach
                                    <td class="px-3 py-3 text-center text-gray-700 dark:text-gray-200">
                                        {{ $row['termsCounted'] > 0 ? \App\Support\Mark::format($row['total'], 2, true).' / '.$row['max'] : 'N/A' }}
                                    </td>
                                    <td class="px-3 py-3 text-center font-bold text-gray-900 dark:text-white">{{ $percent($row['average']) }}</td>
                                    <td class="px-3 py-3 text-center">
                                        <span class="font-extrabold text-gray-900 dark:text-white">{{ $row['grade'] }}</span>
                                        @if ($row['remark'])
                                            <small class="block text-xs text-gray-500 dark:text-gray-400">{{ $row['remark'] }}</small>
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 text-center font-semibold text-gray-900 dark:text-white">{{ $ordinal($row['position']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
</x-dashboard-layout>
