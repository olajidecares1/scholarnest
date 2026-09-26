<x-guardian-layout page-title="Messages" page-subtitle="Notices from your child's school">
    <div class="space-y-4">
        @forelse ($notices as $notice)
            <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $notice->title }}</p>
                    <span class="text-xs text-gray-400">{{ $notice->created_at->diffForHumans() }}</span>
                </div>
                <small class="block mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $notice->body }}</small>
            </div>
        @empty
            <div class="rounded-[10px] border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-sm text-gray-500 dark:text-gray-400">No messages yet.</small>
            </div>
        @endforelse

        @if ($notices->hasPages())
            <div class="pt-2">{{ $notices->links() }}</div>
        @endif
    </div>
</x-guardian-layout>
