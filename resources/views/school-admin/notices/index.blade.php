<x-dashboard-layout page-title="Memorandums" page-subtitle="Send a message to your staff, students or parents.">
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
                            <small class="block mt-1 whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $notice->body }}</small>
                            <small class="block mt-2 text-xs text-gray-400">
                                To {{ $notice->audience?->label() ?? 'Students / Pupils' }}
                                &middot; {{ $notice->class_name ?? 'all classes' }}
                            </small>
                        </div>
                    @empty
                        <small class="block px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No notices sent yet.</small>
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
                <h2 class="text-sm font-bold text-gray-900 dark:text-white">New Memorandum</h2>
                <small class="field-hint mt-1">Delivered to the Messages tab and as a notification in each recipient's portal.</small>

                <form method="POST" action="{{ route('notices.store') }}" class="mt-4 space-y-2">
                    @csrf

                    <x-text-field
                        name="title"
                        label="Title"
                        icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z"
                        :value="old('title')"
                        required
                        placeholder="e.g. Mid-Term Break Notice"
                    />

                    {{-- Who it goes to. "Everyone" is one memorandum, not
                         three written out separately, a school addressing the
                         whole community should not have to say it three times,
                         and a term later the record should still read as one
                         message to everybody. --}}
                    <x-select-field
                        name="audience"
                        label="Send To"
                        selected="{{ old('audience', \App\Enums\MemorandumAudience::All->value) }}"
                        :options="collect($audiences)->mapWithKeys(fn ($audience) => [$audience->value => $audience->label()])->all()"
                        helper="Only the groups your plan has accounts for are listed."
                    />

                    {{-- Narrows the people who belong to a class, students,
                         and the parents of those students. Staff are not in a
                         class, so a memorandum to staff reaches all of them
                         whatever is chosen here. --}}
                    <x-select-field
                        name="class_name"
                        label="Limit to a Class"
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
                        class="btn flex w-full items-center justify-center gap-2 rounded-[8px] bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-blue-600/30 transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-lg"
                    >
                        Send Memorandum
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
