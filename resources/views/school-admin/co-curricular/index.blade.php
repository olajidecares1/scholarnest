<x-dashboard-layout page-title="Co-curricular Activities" page-subtitle="Manage clubs and activities students can join.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">Students can browse and join these from their portal.</p>
            <button
                type="button"
                @click="editing = null; open = true"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Add Activity
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($activities as $activity)
                <div class="rounded-[10px] border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-800">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-bold text-gray-900 dark:text-white">{{ $activity->name }}</p>
                        @if ($activity->category)
                            <span class="shrink-0 rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">{{ $activity->category }}</span>
                        @endif
                    </div>
                    @if ($activity->description)
                        <p class="field-hint mt-1">{{ Str::limit($activity->description, 80) }}</p>
                    @endif
                    @if ($activity->schedule_text)
                        <p class="mt-2 text-xs font-medium text-gray-600 dark:text-gray-300">{{ $activity->schedule_text }}</p>
                    @endif
                    <p class="mt-2 text-xs text-gray-400">{{ $activity->students_count }} student(s) joined</p>

                    <div class="mt-3 flex items-center gap-2">
                        <button
                            type="button"
                            @click="editing = @js([
                                'uuid' => $activity->uuid,
                                'name' => $activity->name,
                                'category' => $activity->category,
                                'description' => $activity->description,
                                'schedule_text' => $activity->schedule_text,
                            ]); open = true"
                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            Edit
                        </button>
                        <form method="POST" action="{{ route('co-curricular.destroy', $activity) }}" onsubmit="return confirm('Remove {{ $activity->name }}?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="col-span-full rounded-[10px] border border-dashed border-gray-300 p-10 text-center dark:border-gray-700">
                    <p class="text-sm text-gray-500 dark:text-gray-400">No activities yet. Click "Add Activity" to get started.</p>
                </div>
            @endforelse
        </div>

        {{-- Add/Edit Activity modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="w-full max-w-lg rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Activity' : 'Add Activity'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('co-curricular.update', ['activity' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('co-curricular.store') }}'"
                    class="mt-4 space-y-2"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <x-text-field name="name" label="Name" icon="M12 4.5l2.1 4.3 4.7.7-3.4 3.3.8 4.7-4.2-2.2-4.2 2.2.8-4.7-3.4-3.3 4.7-.7z" x-model="editing ? editing.name : ''" placeholder="e.g. Debate Club" required />
                    <x-text-field name="category" label="Category" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" x-model="editing ? editing.category : ''" placeholder="e.g. Sports, Arts, Academic" helper="Optional." />
                    <x-text-field name="schedule_text" label="Schedule" icon="M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" x-model="editing ? editing.schedule_text : ''" placeholder="e.g. Every Friday, 2:00 PM" helper="Optional." />
                    <x-textarea-field name="description" label="Description" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="3" x-model="editing ? editing.description : ''" helper="Optional." />

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Activity</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
