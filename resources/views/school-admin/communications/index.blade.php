<x-dashboard-layout page-title="Communications" page-subtitle="Announcements sent to your school from EduNest.">
    <div class="space-y-4">
        @forelse ($announcements as $announcement)
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md lg:rounded-[10px]">
                <div class="flex items-start gap-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-[5px] bg-blue-50 text-blue-600 lg:rounded-[10px]">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" />
                            <path d="M8 10h8M8 13h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" />
                        </svg>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-sm font-bold text-gray-900">{{ $announcement->title }}</h3>
                            <span class="text-xs text-gray-400">{{ $announcement->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-2 text-sm leading-relaxed text-gray-600">{{ $announcement->body }}</p>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-[5px] border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 lg:rounded-[10px]">
                No announcements yet. You&rsquo;ll see platform updates from EduNest here.
            </div>
        @endforelse

        @if ($announcements->hasPages())
            <div class="pt-2">
                {{ $announcements->links() }}
            </div>
        @endif
    </div>
</x-dashboard-layout>
