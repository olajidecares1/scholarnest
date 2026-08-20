<x-dashboard-layout page-title="Notices" page-subtitle="Send messages to your students' portal inbox.">
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if (session('status'))
                <div class="mb-6 rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                    {{ session('status') }}
                </div>
            @endif

            <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <div class="border-b border-gray-100 px-5 py-4 dark:border-gray-700">
                    <h2 class="text-sm font-bold text-gray-900 dark:text-white">Notice History</h2>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse ($notices as $notice)
                        <div class="px-5 py-4">
                            <div class="flex items-center justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900 dark:text-white">{{ $notice->title }}</p>
                                <span class="text-xs text-gray-400">{{ $notice->created_at->diffForHumans() }}</span>
                            </div>
                            <p class="mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $notice->body }}</p>
                            <p class="mt-2 text-xs text-gray-400">To {{ $notice->class_name ?? 'all classes' }}</p>
                        </div>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No notices sent yet.</p>
                    @endforelse
                </div>

                @if ($notices->hasPages())
                    <div class="border-t border-gray-100 px-5 py-4 dark:border-gray-700">
                        {{ $notices->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div>
            <div class="rounded-[5px] border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">New Notice</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Delivered to the Messages tab and as a notification in each student's portal.</p>

                <form method="POST" action="{{ route('notices.store') }}" class="mt-4 space-y-4">
                    @csrf

                    <x-text-field
                        name="title"
                        label="Title"
                        icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                        :value="old('title')"
                        required
                        placeholder="e.g. Mid-Term Break Notice"
                    />

                    <x-select-field
                        name="class_name"
                        label="Audience"
                        selected="{{ old('class_name') }}"
                        placeholder="All Classes"
                        :options="['' => 'All Classes'] + $academicLevels->flatMap->classes->pluck('name', 'name')->all()"
                    />

                    <x-textarea-field
                        name="body"
                        label="Message"
                        icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                        rows="5"
                        required
                        placeholder="Write your message..."
                        :value="old('body')"
                    />

                    <button
                        type="submit"
                        class="flex w-full items-center justify-center gap-2 rounded-[8px] bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-blue-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg"
                    >
                        Send Notice
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
