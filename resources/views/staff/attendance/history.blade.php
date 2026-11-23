<x-staff-layout page-title="Attendance History" page-subtitle="Aggregated attendance for your class.">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            @include('staff.attendance._tabs', ['current' => $period === 'term' ? 'term-history' : $period])
            <div class="flex items-center gap-3">
                @if ($classes->count() > 1)
                    <form method="GET">
                        <input type="hidden" name="period" value="{{ $period }}">
                        <select name="class" onchange="this.form.submit()" >
                            @foreach ($classes as $class)
                                <option value="{{ $class }}" @selected($className === $class)>{{ $class }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $rangeLabel }}</span>
            </div>
        </div>

        @if ($classes->isEmpty())
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">You haven't been assigned as a Class Teacher yet</p>
            </div>
        @elseif (! $termConfigured)
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">Term dates haven't been set by your School Admin yet</p>
                <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">Termly attendance will show here once term dates are configured.</small>
                <a href="{{ route('staff.attendance.term.index', [$school, 'class' => $className]) }}" class="btn mt-4 inline-flex rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                    <i class="fa-solid fa-pen-to-square btn-icon" aria-hidden="true"></i> Enter Term Totals instead
                </a>
                <small class="mt-2 block text-xs text-gray-500 dark:text-gray-400">Type each student's school days, days present and absent for the term. Term dates aren't needed.</small>
            </div>
        @else
            {{-- On a phone: one card per student/pupil, with a gap between
                 them, the figures in a small grid. The table squeezed every
                 heading and name into single letters at that width. --}}
            <div class="space-y-3 sm:hidden">
                @forelse ($summaries as $row)
                    <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                        <div class="flex items-start justify-between gap-3">
                            <p class="min-w-0 font-semibold text-gray-900 dark:text-white">
                                <span class="tabular-nums text-gray-400">{{ $loop->iteration }}.</span> {{ $row['student']->fullName() }}
                            </p>
                            <span class="shrink-0 rounded-full bg-blue-50 px-2.5 py-0.5 text-sm font-bold tabular-nums text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">
                                {{ $row['percent'] !== null ? $row['percent'].'%' : 'N/A' }}
                            </span>
                        </div>
                        <dl class="mt-3 grid grid-cols-5 gap-1.5 text-center">
                            @foreach ([
                                ['Present', $row['present'], 'text-green-600 dark:text-green-400'],
                                ['Absent', $row['absent'], 'text-red-600 dark:text-red-400'],
                                ['Late', $row['late'], 'text-amber-600 dark:text-amber-400'],
                                ['Excused', $row['excused'], 'text-blue-600 dark:text-blue-400'],
                                ['Marked', $row['total'], 'text-gray-500 dark:text-gray-400'],
                            ] as [$label, $value, $tone])
                                <div class="rounded-[8px] bg-gray-50 px-1 py-2 dark:bg-gray-900/40">
                                    <dt class="text-[10px] font-semibold uppercase tracking-wide {{ $tone }}">{{ $label }}</dt>
                                    <dd class="mt-0.5 text-base font-bold tabular-nums text-gray-900 dark:text-white">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @empty
                    <small class="block rounded-[10px] border border-gray-200 bg-white px-4 py-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400">No active students yet.</small>
                @endforelse
            </div>

            {{-- From a small tablet up: the table, nothing wrapping, scrolling
                 sideways if it is still wider than the screen. --}}
            <div class="hidden overflow-x-auto rounded-[10px] border border-gray-200 bg-white shadow-sm sm:block dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[40rem] text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="w-12 whitespace-nowrap px-4 py-3 font-semibold">S/N</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Student</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Present</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Absent</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Late</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Excused</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Marked</th>
                                <th class="whitespace-nowrap px-4 py-3 font-semibold">Attendance %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($summaries as $row)
                                <tr>
                                    <td class="w-12 whitespace-nowrap px-4 py-3 tabular-nums text-gray-500 dark:text-gray-400">{{ $loop->iteration }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $row['student']->fullName() }}</td>
                                    <td class="px-4 py-3 tabular-nums text-gray-600 dark:text-gray-300">{{ $row['present'] }}</td>
                                    <td class="px-4 py-3 tabular-nums text-gray-600 dark:text-gray-300">{{ $row['absent'] }}</td>
                                    <td class="px-4 py-3 tabular-nums text-gray-600 dark:text-gray-300">{{ $row['late'] }}</td>
                                    <td class="px-4 py-3 tabular-nums text-gray-600 dark:text-gray-300">{{ $row['excused'] }}</td>
                                    <td class="px-4 py-3 tabular-nums text-gray-600 dark:text-gray-300">{{ $row['total'] }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 tabular-nums text-gray-600 dark:text-gray-300">{{ $row['percent'] !== null ? $row['percent'].'%' : 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-staff-layout>
