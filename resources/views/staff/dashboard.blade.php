<x-staff-layout page-title="Dashboard" :page-subtitle="'Welcome back, '.$staff->first_name.'!'">
    <div class="space-y-6">
        <x-welcome-banner
            :photo-url="$staff->photoUrl()"
            :initials="Str::of($staff->first_name)->substr(0, 1)->upper()"
            :name="$staff->fullName()"
            :role-label="$staff->role->label()"
            subtitle="Have a productive day at work."
        />

        <div class="grid grid-cols-4 gap-2.5 sm:grid-cols-5 sm:gap-3 md:grid-cols-6 lg:grid-cols-4 lg:gap-4 xl:grid-cols-5">
            @foreach ([
                ['route' => 'staff.profile', 'label' => 'My Profile', 'icon' => 'fa-user', 'color' => 'text-blue-600 bg-blue-50 dark:bg-blue-900/20'],
                ['route' => 'staff.timetable', 'label' => 'My Timetable', 'icon' => 'fa-calendar-days', 'color' => 'text-cyan-600 bg-cyan-50 dark:bg-cyan-900/20'],
                ...($staff->role === \App\Enums\StaffRole::Teacher ? [
                    ['route' => 'staff.attendance.index', 'label' => 'Attendance', 'icon' => 'fa-clipboard-check', 'color' => 'text-green-600 bg-green-50 dark:bg-green-900/20'],
                    ['route' => 'staff.exams.index', 'label' => 'Exam Scores', 'icon' => 'fa-file-pen', 'color' => 'text-orange-600 bg-orange-50 dark:bg-orange-900/20'],
                    ['route' => 'staff.results.index', 'label' => 'Report Cards', 'icon' => 'fa-file-lines', 'color' => 'text-rose-600 bg-rose-50 dark:bg-rose-900/20'],
                    ['route' => 'staff.cbt.tests.index', 'label' => 'CBT Management', 'icon' => 'fa-laptop-code', 'color' => 'text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20'],
                ] : []),
                ['route' => 'staff.settings.index', 'label' => 'Settings', 'icon' => 'fa-gear', 'color' => 'text-gray-600 bg-gray-100 dark:bg-gray-700'],
                ['route' => 'staff.help.index', 'label' => 'Help & Support', 'icon' => 'fa-circle-question', 'color' => 'text-teal-600 bg-teal-50 dark:bg-teal-900/20'],
            ] as $item)
                @continue(! \Illuminate\Support\Facades\Route::has($item['route']))
                <x-module-card :href="route($item['route'], $school)" :label="$item['label']" :icon="$item['icon']" :color="$item['color']" />
            @endforeach
        </div>

        @php
            $classTeacherOf = $staff->classesAsClassTeacher();
            $subjectAssignments = $staff->subjectAssignments();
        @endphp

        @if ($classTeacherOf->isNotEmpty() && \Illuminate\Support\Facades\Route::has('staff.attendance.index'))
            <a href="{{ route('staff.attendance.index', $school) }}" class="flex items-center justify-between gap-3 rounded-[10px] border border-green-200 bg-green-50 p-5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:shadow-md dark:border-green-900/40 dark:bg-green-900/10">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[8px] bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400">
                        <i class="fa-solid fa-clipboard-check"></i>
                    </span>
                    <div>
                        <p class="text-sm font-bold text-gray-900 dark:text-white">Take Today's Attendance</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">You're the Class Teacher of {{ $classTeacherOf->implode(', ') }}.</p>
                    </div>
                </div>
                <i class="fa-solid fa-chevron-right text-gray-400"></i>
            </a>
        @endif

        @if ($staff->role === \App\Enums\StaffRole::Teacher && ($classTeacherOf->isNotEmpty() || $subjectAssignments->isNotEmpty()))
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">My Classes</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Classes you're the Class Teacher of.</p>
                    <div class="mt-3 flex flex-wrap gap-2">
                        @forelse ($classTeacherOf as $class)
                            <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700 dark:bg-green-900/20 dark:text-green-400">{{ $class }}</span>
                        @empty
                            <p class="text-xs text-gray-500 dark:text-gray-400">Not assigned to any class yet.</p>
                        @endforelse
                    </div>
                </div>
                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">My Subjects</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Subjects you've been assigned to teach.</p>
                    <div class="mt-3 space-y-1.5">
                        @forelse ($subjectAssignments->groupBy('subject') as $subject => $classes)
                            <div class="flex items-center justify-between gap-2 text-xs">
                                <span class="font-semibold text-gray-900 dark:text-white">{{ $subject }}</span>
                                <span class="text-gray-500 dark:text-gray-400">{{ $classes->pluck('class_name')->implode(', ') }}</span>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500 dark:text-gray-400">Not assigned to any subject yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endif

        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Today's Classes</h2>
                @if (\Illuminate\Support\Facades\Route::has('staff.timetable'))
                    <a href="{{ route('staff.timetable', $school) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700">View full timetable</a>
                @endif
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($todaysClasses as $entry)
                    <div class="flex items-center justify-between gap-3 rounded-[8px] bg-gray-50 p-3 dark:bg-gray-900/40">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ $entry->subject }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->class_name }}@if ($entry->room) &middot; {{ $entry->room }} @endif</p>
                        </div>
                        <span class="shrink-0 text-xs font-semibold text-gray-500 dark:text-gray-400">
                            {{ \Illuminate\Support\Carbon::parse($entry->start_time)->format('h:i A') }} – {{ \Illuminate\Support\Carbon::parse($entry->end_time)->format('h:i A') }}
                        </span>
                    </div>
                @empty
                    <p class="rounded-[8px] border border-dashed border-gray-200 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No classes scheduled for today.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-staff-layout>
