@php
    $statusOptions = \App\Enums\AttendanceStatus::cases();
    $markedCount = $existing->count();
    $presentCount = $existing->filter(fn ($record) => $record->status->isPresentForStats())->count();

    // Written out as complete, literal peer-checked:* strings (not built via
    // concatenation) so Tailwind's v4 content scanner actually picks them up.
    // This matches the exact same reasoning already documented in the
    // School Admin attendance view this page mirrors.
    $statusSelectedClasses = [
        \App\Enums\AttendanceStatus::Present->value => 'peer-checked:bg-green-500 peer-checked:shadow-green-500/30',
        \App\Enums\AttendanceStatus::Absent->value => 'peer-checked:bg-red-500 peer-checked:shadow-red-500/30',
        \App\Enums\AttendanceStatus::Late->value => 'peer-checked:bg-amber-500 peer-checked:shadow-amber-500/30',
        \App\Enums\AttendanceStatus::Excused->value => 'peer-checked:bg-blue-500 peer-checked:shadow-blue-500/30',
    ];
@endphp

<x-staff-layout page-title="Attendance" page-subtitle="Take and review attendance for your class.">
    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        @if ($classes->isEmpty())
            <div class="rounded-[10px] border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm font-semibold text-gray-700 dark:text-gray-200">You haven't been assigned as a Class Teacher yet</p>
                <small class="block mt-1 text-sm text-gray-500 dark:text-gray-400">Ask your School Admin to assign you as the Class Teacher of a class to start taking attendance.</small>
            </div>
        @else
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex gap-2 rounded-[8px] border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-800">
                    <span class="rounded-[6px] bg-blue-600 px-4 py-1.5 text-sm font-semibold text-white">Daily</span>
                    <a href="{{ route('staff.attendance.history', [$school, 'period' => 'week', 'class' => $className]) }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Weekly</a>
                    <a href="{{ route('staff.attendance.history', [$school, 'period' => 'month', 'class' => $className]) }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Monthly</a>
                    <a href="{{ route('staff.attendance.history', [$school, 'period' => 'term', 'class' => $className]) }}" class="rounded-[6px] px-4 py-1.5 text-sm font-semibold text-gray-600 transition-colors duration-150 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-700">Termly</a>
                </div>
                @if ($classes->count() > 1)
                    <form method="GET">
                        <select name="class" onchange="this.form.submit()" >
                            @foreach ($classes as $class)
                                <option value="{{ $class }}" @selected($className === $class)>{{ $class }}</option>
                            @endforeach
                        </select>
                    </form>
                @else
                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $className }}</span>
                @endif
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-purple-600">Students in {{ $className }}</p>
                    <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($students->count()) }}</p>
                </div>
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-blue-600">Marked for {{ $date->format('M j') }}</p>
                    <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($markedCount) }}</p>
                </div>
                <div class="rounded-[5px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                    <p class="text-sm font-medium text-green-600">Present / Late</p>
                    <p class="mt-2 text-3xl font-extrabold text-gray-900 dark:text-white">{{ number_format($presentCount) }}</p>
                </div>
            </div>

            <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 p-6 dark:border-gray-700">
                    <form method="GET">
                        <input type="hidden" name="class" value="{{ $className }}">
                        <input
                            type="date"
                            name="date"
                            value="{{ $date->toDateString() }}"
                            max="{{ today()->toDateString() }}"
                            onchange="this.form.submit()"
                        >
                    </form>
                </div>

                <form method="POST" action="{{ route('staff.attendance.store', $school) }}">
                    @csrf
                    <input type="hidden" name="date" value="{{ $date->toDateString() }}">
                    <input type="hidden" name="class_name" value="{{ $className }}">

                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                                <tr>
                                    <th class="px-6 py-3 font-semibold">Student</th>
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
                                        <td colspan="2" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No active students in {{ $className }} yet.</td>
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
        @endif
    </div>
</x-staff-layout>
