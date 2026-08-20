<x-student-layout page-title="Messages" page-subtitle="Notices from your school">
    <div class="space-y-4">
        @forelse ($notices as $notice)
            <a href="{{ route('student.messages.show', [$school, $notice]) }}" class="block rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm transition-all duration-300 ease-out hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-800">
                <div class="flex items-start gap-3">
                    @unless ($readIds->contains($notice->id))
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500"></span>
                    @else
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-transparent"></span>
                    @endunless
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $notice->title }}</p>
                            <span class="text-xs text-gray-400">{{ $notice->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-1 line-clamp-2 text-sm text-gray-600 dark:text-gray-300">{{ $notice->body }}</p>
                    </div>
                </div>
            </a>
        @empty
            <div class="rounded-[10px] border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-800">
                <p class="text-sm text-gray-500 dark:text-gray-400">No messages yet.</p>
            </div>
        @endforelse

        @if ($notices->hasPages())
            <div class="pt-2">{{ $notices->links() }}</div>
        @endif
    </div>
</x-student-layout>
