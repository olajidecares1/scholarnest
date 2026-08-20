<x-guardian-layout page-title="Dashboard" :page-subtitle="'Welcome back, '.$guardian->name.'!'" :active-child="$activeChild">
    <div class="space-y-6">
        <x-welcome-banner
            :photo-url="$guardian->photoUrl()"
            :initials="Str::of($guardian->name)->substr(0, 1)->upper()"
            :name="$guardian->name"
            role-label="Parent / Guardian"
            :subtitle="'Here\'s an overview of '.$activeChild->first_name.'\'s school life.'"
        />

        @if ($children->count() > 1)
            <div class="flex flex-wrap gap-2">
                @foreach ($children as $child)
                    <a
                        href="{{ route('guardian.dashboard', $school) }}?child={{ $child->uuid }}"
                        class="rounded-full border px-4 py-2 text-sm font-semibold transition-all duration-200 {{ $child->is($activeChild) ? 'border-primary-600 bg-primary-600 text-white' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200' }}"
                    >
                        {{ $child->fullName() }}
                    </a>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-4 gap-2.5 sm:grid-cols-5 sm:gap-3 md:grid-cols-6 lg:grid-cols-4 lg:gap-4 xl:grid-cols-5">
            <x-module-card :href="route('guardian.children.profile', [$school, $activeChild])" label="Child Profile" icon="fa-user" color="text-blue-600 bg-blue-50 dark:bg-blue-900/20" />
            <x-module-card :href="route('guardian.children.timetable', [$school, $activeChild])" label="Timetable" icon="fa-calendar-days" color="text-cyan-600 bg-cyan-50 dark:bg-cyan-900/20" />
            <x-module-card :href="route('guardian.children.results', [$school, $activeChild])" label="Exams & Results" icon="fa-file-pen" color="text-amber-600 bg-amber-50 dark:bg-amber-900/20" />
            <x-module-card :href="route('guardian.children.attendance', [$school, $activeChild])" label="Attendance" icon="fa-clipboard-check" color="text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20" />
            <x-module-card :href="route('guardian.children.assignments', [$school, $activeChild])" label="Assignments" icon="fa-list-check" color="text-rose-600 bg-rose-50 dark:bg-rose-900/20" />
            <x-module-card :href="route('guardian.children.fees', [$school, $activeChild])" label="Fees & Payments" icon="fa-sack-dollar" color="text-red-600 bg-red-50 dark:bg-red-900/20" />
            <x-module-card :href="route('guardian.messages.index', $school)" label="Messages" icon="fa-envelope" color="text-purple-600 bg-purple-50 dark:bg-purple-900/20" />
            <x-module-card :href="route('guardian.settings.index', $school)" label="Settings" icon="fa-gear" color="text-gray-600 bg-gray-100 dark:bg-gray-700" />
            <x-module-card :href="route('guardian.help.index', $school)" label="Help & Support" icon="fa-circle-question" color="text-indigo-600 bg-indigo-50 dark:bg-indigo-900/20" />
        </div>

        <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">Recent Notices</h2>
                <a href="{{ route('guardian.messages.index', $school) }}" class="text-xs font-semibold text-primary-600 hover:text-primary-700">View all</a>
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
</x-guardian-layout>
