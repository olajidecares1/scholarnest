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

        {{-- The home screen: every feature this pupil may use, grouped,
             as an icon over its name. It replaced a grid of large cards
             that were navigation dressed as content - four of them filled
             a phone screen, so Results was three screens down. --}}
        @php
            $nav = \App\Support\PortalNavigation::forStudent($student);
            $badges = array_filter([
                'student.notifications.index' => $unreadNotificationsCount,
                'student.messages.index' => $unreadNoticesCount,
                'student.assignments.index' => $pendingAssignmentsCount,
            ]);
        @endphp

        <x-portal-app-menu :categories="$nav['categories']" :badges="$badges" />

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
