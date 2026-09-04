<x-guardian-layout page-title="Dashboard" :page-subtitle="'Welcome back, '.$guardian->name.'!'" :active-child="$activeChild">
    <div class="space-y-4 sm:space-y-6">
        <x-welcome-banner
            compact
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

        {{-- Every academic entry below is bound to the child selected above, so
             "Check Result" opens THAT child's results and the token stays tied
             to that child. Switching child switches the whole menu. --}}
        @php $nav = \App\Support\PortalNavigation::forGuardian($guardian, $activeChild); @endphp

        <x-portal-app-menu :categories="$nav['categories']" />

        <div class="rounded-[10px] border border-gray-200 bg-white p-4 shadow-sm sm:p-5 dark:border-gray-700 dark:bg-gray-800">
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
