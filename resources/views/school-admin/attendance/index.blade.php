@php
    $statusOptions = \App\Enums\AttendanceStatus::cases();
    $markedCount = $existing->count();
    $presentCount = $existing->filter(fn ($record) => $record->status->isPresentForStats())->count();

    // Written out as complete, literal peer-checked:* strings (not built via
    // concatenation) so Tailwind's v4 content scanner, which only generates
    // CSS for class names it finds as contiguous text in scanned source
    // files, actually picks these up. A "peer-checked:" prefix combined
    // with a PHP-computed suffix at render time produces no such literal
    // anywhere, so no CSS would ever be generated for it.
    $statusSelectedClasses = [
        \App\Enums\AttendanceStatus::Present->value => 'peer-checked:bg-green-500 peer-checked:shadow-green-500/30',
        \App\Enums\AttendanceStatus::Absent->value => 'peer-checked:bg-red-500 peer-checked:shadow-red-500/30',
        \App\Enums\AttendanceStatus::Late->value => 'peer-checked:bg-amber-500 peer-checked:shadow-amber-500/30',
        \App\Enums\AttendanceStatus::Excused->value => 'peer-checked:bg-blue-500 peer-checked:shadow-blue-500/30',
    ];
@endphp

<x-dashboard-layout page-title="Attendance" page-subtitle="Take and review daily student attendance.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            @include('school-admin.attendance._tabs', ['current' => 'index'])
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-purple-600">Students Listed</p>
                    <x-stat-tooltip text="Active students in the class you've filtered to (or all classes if none selected)." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($students->count()) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-blue-600">Marked for {{ $date->format('M j') }}</p>
                    <x-stat-tooltip text="How many of the students listed already have an attendance record saved for this date." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($markedCount) }}</p>
            </div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex items-center gap-1">
                    <p class="text-sm font-medium text-green-600">Present / Late</p>
                    <x-stat-tooltip text="Students marked Present or Late for this date out of those already marked." />
                </div>
                <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($presentCount) }}</p>
            </div>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                <form method="GET" class="flex flex-wrap items-end gap-2">
                    <input
                        type="date"
                        name="date"
                        value="{{ $date->toDateString() }}"
                        max="{{ today()->toDateString() }}"
                        onchange="this.form.submit()"
                    >
                    <div
                        class="w-48"
                        x-data="{ classFilter: @js($className) }"
                        x-init="$watch('classFilter', () => $el.closest('form').submit())"
                    >
                        <x-select-field
                            name="class"
                            model="classFilter"
                            placeholder="All Classes"
                            :options="['' => 'All Classes'] + $academicLevels->flatMap->classes->pluck('name', 'name')->all()"
                        />
                    </div>
                </form>
            </div>

            <form method="POST" action="{{ route('attendance.store') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date->toDateString() }}">

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                            <tr>
                                <th class="px-6 py-3 font-semibold">Student</th>
                                <th class="px-6 py-3 font-semibold">Class</th>
                                <th class="px-6 py-3 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($students as $student)
                                @php $current = $existing->get($student->id)?->status ?? \App\Enums\AttendanceStatus::Present; @endphp
                                <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-6 py-3">
                                        <div class="flex items-center gap-3">
                                            @if ($student->photoUrl())
                                                <img src="{{ $student->photoUrl() }}" class="h-9 w-9 rounded-full object-cover">
                                            @else
                                                <span class="flex h-9 w-9 items-center justify-center rounded-full bg-blue-100 text-sm font-bold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">
                                                    {{ Str::of($student->first_name)->substr(0, 1)->upper() }}{{ Str::of($student->last_name)->substr(0, 1)->upper() }}
                                                </span>
                                            @endif
                                            <span class="font-semibold text-gray-900 dark:text-white">{{ $student->fullName() }}</span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $student->class_name ?? 'N/A' }}</td>
                                    <td class="px-6 py-3">
                                        <div class="grid max-w-md grid-cols-4 gap-1.5">
                                            @foreach ($statusOptions as $status)
                                                <label class="cursor-pointer">
                                                    <input type="radio" name="records[{{ $student->id }}]" value="{{ $status->value }}" class="peer sr-only" @checked($current === $status)>
                                                    <small class="block rounded-[6px] border border-gray-300 bg-white px-2 py-1.5 text-center text-xs font-semibold text-gray-500 transition-all duration-200 ease-out hover:-translate-y-0.5 hover:shadow-sm active:scale-95 peer-checked:border-transparent peer-checked:text-white peer-checked:shadow-md dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 {{ $statusSelectedClasses[$status->value] }}">
                                                        {{ $status->label() }}
                                                    </small>
                                                </label>
                                            @endforeach
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                        No active students @if ($className) in {{ $className }} @endif to mark attendance for.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($students->isNotEmpty())
                    <div class="flex justify-end border-t border-gray-100 p-6 dark:border-gray-700">
                        <button type="submit" class="btn rounded-[8px] bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md">
                            Save Attendance for {{ $date->format('M j, Y') }}
                        </button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</x-dashboard-layout>
