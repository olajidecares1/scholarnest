<x-student-layout page-title="Notifications" page-subtitle="Recent activity on your portal">
    <div class="space-y-4">
        @forelse ($notifications as $notification)
            {{-- A notification that names somewhere to go becomes a link to it.
                 Everything written before this carries no url and renders
                 exactly as it did. --}}
            @php($destination = $notification->data['url'] ?? null)

            <{{ $destination ? 'a' : 'div' }}
                @if ($destination) href="{{ $destination }}" @endif
                class="block rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800 @if ($destination) transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md @endif"
            >
                <div class="flex items-start gap-3">
                    @if (is_null($notification->read_at))
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary-500"></span>
                    @else
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-transparent"></span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $notification->data['title'] ?? 'Notification' }}</p>
                            <span class="text-xs text-gray-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </div>
                        <small class="block mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $notification->data['body'] ?? '' }}</small>

                        @if ($destination)
                            <span class="mt-2 inline-flex items-center gap-1.5 text-[11.5px] font-bold text-primary-600 dark:text-primary-400">
                                Open <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </span>
                        @endif
                    </div>
                </div>
            </{{ $destination ? 'a' : 'div' }}>
        @empty
            <div class="rounded-[10px] border border-dashed border-gray-200 bg-white py-16 text-center dark:border-gray-700 dark:bg-gray-800">
                <small class="block text-sm text-gray-500 dark:text-gray-400">No notifications yet.</small>
            </div>
        @endforelse

        @if ($notifications->hasPages())
            <div class="pt-2">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-student-layout>
