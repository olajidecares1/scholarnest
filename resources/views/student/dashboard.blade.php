<x-student-layout page-title="Dashboard" :page-subtitle="'Welcome back, '.$student->first_name.'!'">
    <div class="space-y-6">
        {{-- Welcome banner --}}
        <x-welcome-banner
            :photo-url="$student->photoUrl()"
            :initials="Str::of($student->first_name)->substr(0, 1)->upper()"
            :name="$student->fullName()"
            role-label="Student"
            subtitle="Stay consistent and never stop learning."
        />

        {{-- Module grid --}}
        <div class="grid grid-cols-4 gap-2.5 sm:grid-cols-5 sm:gap-3 md:grid-cols-6 lg:grid-cols-4 lg:gap-4 xl:grid-cols-5">
            @foreach ([
                ['route' => 'student.profile', 'label' => 'My Profile', 'icon' => 'fa-user', 'color' => 'text-blue-600 bg-blue-50 dark:bg-blue-900/20'],
                ['route' => 'student.timetable', 'label' => 'My Timetable', 'icon' => 'fa-calendar-days', 'color' => 'text-cyan-600 bg-cyan-50 dark:bg-cyan-900/20'],
                ['route' => 'student.subjects', 'label' => 'My Subjects', 'icon' => 'fa-book', 'color' => 'text-rose-600 bg-rose-50 dark:bg-rose-900/20'],
                ['route' => 'student.assignments.index', 'label' => 'Assignments', 'icon' => 'fa-list-check', 'color' => 'text-amber-600 bg-amber-50 dark:bg-amber-900/20', 'badge' => $pendingAssignmentsCount > 0 ? $pendingAssignmentsCount : null],
                ['route' => 'student.results.index', 'label' => 'Exams & Results', 'icon' => 'fa-file-pen', 'color' => 'text-purple-600 bg-purple-50 dark:bg-purple-900/20'],
                ['route' => 'student.attendance.index', 'label' => 'Attendance', 'icon' => 'fa-clipboard-check', 'color' => 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20'],
                ['route' => 'student.library.index', 'label' => 'Library', 'icon' => 'fa-book-open-reader', 'color' => 'text-lime-600 bg-lime-50 dark:bg-lime-900/20'],
                ['route' => 'student.cbt-practice.index', 'label' => 'CBT Practice', 'icon' => 'fa-laptop-code', 'color' => 'text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20'],
                ['route' => 'student.tests.index', 'label' => 'My Tests', 'icon' => 'fa-file-circle-check', 'color' => 'text-violet-600 bg-violet-50 dark:bg-violet-900/20'],
                ['route' => 'student.messages.index', 'label' => 'Messages', 'icon' => 'fa-envelope', 'color' => 'text-sky-600 bg-sky-50 dark:bg-sky-900/20', 'badge' => $unreadNoticesCount > 0 ? $unreadNoticesCount : null],
                ['route' => 'student.notifications.index', 'label' => 'Notifications', 'icon' => 'fa-bell', 'color' => 'text-orange-600 bg-orange-50 dark:bg-orange-900/20', 'badge' => $unreadNotificationsCount > 0 ? $unreadNotificationsCount : null],
                ['route' => 'student.co-curricular.index', 'label' => 'Co-curricular', 'icon' => 'fa-medal', 'color' => 'text-fuchsia-600 bg-fuchsia-50 dark:bg-fuchsia-900/20'],
                ['route' => 'student.settings.index', 'label' => 'Settings', 'icon' => 'fa-gear', 'color' => 'text-gray-600 bg-gray-100 dark:bg-gray-700'],
                ['route' => 'student.help.index', 'label' => 'Help & Support', 'icon' => 'fa-circle-question', 'color' => 'text-teal-600 bg-teal-50 dark:bg-teal-900/20'],
            ] as $item)
                @continue(! \Illuminate\Support\Facades\Route::has($item['route']))
                <x-module-card
                    :href="route($item['route'], $school)"
                    :label="$item['label']"
                    :icon="$item['icon']"
                    :color="$item['color']"
                    :badge="$item['badge'] ?? null"
                />
            @endforeach
        </div>

        {{-- Recent Notices --}}
        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent Notices</h2>
                @if (\Illuminate\Support\Facades\Route::has('student.messages.index'))
                    <a href="{{ route('student.messages.index', $school) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700">View all</a>
                @endif
            </div>
            <div class="mt-4 space-y-3">
                @forelse ($recentNotices as $notice)
                    <div class="flex items-start gap-3 rounded-[8px] bg-gray-50 p-3 dark:bg-gray-900/40">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500"></span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-gray-900 dark:text-white">{{ $notice->title }}</p>
                            <p class="line-clamp-1 text-xs text-gray-500 dark:text-gray-400">{{ $notice->body }}</p>
                            <p class="mt-0.5 text-xs text-gray-400">{{ $notice->created_at->format('M j, Y') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="rounded-[8px] border border-dashed border-gray-200 py-8 text-center text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">No notices yet.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-student-layout>
