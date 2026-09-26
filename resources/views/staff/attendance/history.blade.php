<x-staff-layout page-title="Attendance History" page-subtitle="Aggregated attendance for your class.">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2 rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                <a href="{{ route('staff.attendance.index', [$school, 'class' => $className]) }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Daily</a>
                <a href="{{ route('staff.attendance.history', [$school, 'period' => 'week', 'class' => $className]) }}" class="btn rounded-[6px] px-4 py-1.5 text-sm font-semibold {{ $period === 'week' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700' }}">Weekly</a>
                <a href="{{ route('staff.attendance.history', [$school, 'period' => 'month', 'class' => $className]) }}" class="btn rounded-[6px] px-4 py-1.5 text-sm font-semibold {{ $period === 'month' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700' }}">Monthly</a>
                <a href="{{ route('staff.attendance.history', [$school, 'period' => 'term', 'class' => $className]) }}" class="btn rounded-[6px] px-4 py-1.5 text-sm font-semibold {{ $period === 'term' ? 'bg-blue-600 text-white' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700' }}">Termly</a>
            </div>
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
            </div>
        @else
            <div class="overflow-x-auto rounded-[10px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-900/40 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold">Student</th>
                                <th class="px-4 py-3 font-semibold">Present</th>
                                <th class="px-4 py-3 font-semibold">Absent</th>
                                <th class="px-4 py-3 font-semibold">Late</th>
                                <th class="px-4 py-3 font-semibold">Excused</th>
                                <th class="px-4 py-3 font-semibold">Marked</th>
                                <th class="px-4 py-3 font-semibold">Attendance %</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($summaries as $row)
                                <tr>
                                    <td class="px-4 py-3 font-semibold text-gray-900 dark:text-white">{{ $row['student']->fullName() }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['present'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['absent'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['late'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['excused'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['total'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['percent'] !== null ? $row['percent'].'%' : 'N/A' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-staff-layout>
