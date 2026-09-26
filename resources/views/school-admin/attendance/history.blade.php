@php
    $statusOptions = \App\Enums\AttendanceStatus::cases();
@endphp

<x-dashboard-layout page-title="Attendance History" page-subtitle="Browse past attendance records.">
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            @include('school-admin.attendance._tabs', ['current' => 'history'])
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="border-b border-gray-100 p-6 dark:border-gray-700">
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <input
                        type="text"
                        name="search"
                        value="{{ request('search') }}"
                        placeholder="Search student name..."
                        class="w-56"
                    >
                    <div class="w-44">
                        <x-select-field
                            name="class"
                            placeholder="All Classes"
                            :selected="request('class')"
                            :options="['' => 'All Classes'] + $academicLevels->flatMap->classes->pluck('name', 'name')->all()"
                        />
                    </div>
                    <input type="date" name="from" value="{{ request('from') }}" max="{{ today()->toDateString() }}" >
                    <input type="date" name="to" value="{{ request('to') }}" max="{{ today()->toDateString() }}" >
                    <button type="submit" class="btn h-11 rounded-[8px] border border-gray-300 px-4 text-sm font-semibold text-gray-700 transition-all duration-200 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">Filter</button>
                    @if (request('search') || request('class') || request('from') || request('to'))
                        <a href="{{ route('attendance.history') }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700">Clear</a>
                    @endif
                </form>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Date</th>
                            <th class="px-6 py-3 font-semibold">Student</th>
                            <th class="px-6 py-3 font-semibold">Class</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Arrived</th>
                            <th class="px-6 py-3 font-semibold">Left</th>
                            <th class="px-6 py-3 font-semibold">Marked By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($records as $record)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $record->date->format('M j, Y') }}</td>
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $record->student?->fullName() ?? 'N/A' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $record->class_name ?? 'N/A' }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $record->status->badgeClasses() }}">{{ $record->status->label() }}</span>
                                </td>
                                {{-- Filled in by a QR scan at the gate; a dash on a register marked by hand. --}}
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $record->arrived_at?->format('g:i:sa') ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $record->departed_at?->format('g:i:sa') ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-500 dark:text-gray-400">{{ $record->markedBy?->name ?? ($record->source === 'qr' ? 'QR check-in' : 'N/A') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No attendance records match your filters.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($records->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $records->links() }}
                </div>
            @endif
        </div>
    </div>
</x-dashboard-layout>
