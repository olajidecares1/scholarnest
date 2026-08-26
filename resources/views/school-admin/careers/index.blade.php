@php
    $typeOptions = collect($employmentTypes)->mapWithKeys(fn ($t) => [$t->value => $t->label()])->all();
@endphp

<x-dashboard-layout page-title="Careers" page-subtitle="Post job openings on your public website.">
    <div class="space-y-6" x-data="{ open: false, editing: null }">
        @if (session('status'))
            <div class="rounded-[5px] bg-green-50 p-4 text-sm font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400 lg:rounded-[10px]">
                {{ session('status') }}
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500 dark:text-gray-400">Open roles appear in the "Job Portal" section of your public website.</p>
            <button
                type="button"
                @click="editing = null; open = true"
                class="flex items-center gap-2 rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-all duration-300 ease-out hover:-translate-y-0.5 hover:bg-blue-700 hover:shadow-md"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>
                Post a Job
            </button>
        </div>

        <div class="rounded-[5px] border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800 lg:rounded-[10px]">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-700/50 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3 font-semibold">Title</th>
                            <th class="px-6 py-3 font-semibold">Department</th>
                            <th class="px-6 py-3 font-semibold">Type</th>
                            <th class="px-6 py-3 font-semibold">Closes</th>
                            <th class="px-6 py-3 font-semibold">Status</th>
                            <th class="px-6 py-3 font-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($jobs as $job)
                            <tr class="transition-colors duration-200 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                <td class="px-6 py-3 font-semibold text-gray-900 dark:text-white">{{ $job->title }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $job->department ?? '—' }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $job->employment_type->label() }}</td>
                                <td class="px-6 py-3 text-gray-600 dark:text-gray-300">{{ $job->closes_at?->format('M j, Y') ?? '—' }}</td>
                                <td class="px-6 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $job->is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' }}">
                                        {{ $job->is_active ? 'Open' : 'Closed' }}
                                    </span>
                                </td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            @click="editing = @js([
                                                'uuid' => $job->uuid,
                                                'title' => $job->title,
                                                'department' => $job->department,
                                                'employment_type' => $job->employment_type->value,
                                                'location' => $job->location,
                                                'description' => $job->description,
                                                'posted_at' => $job->posted_at->format('Y-m-d'),
                                                'closes_at' => $job->closes_at?->format('Y-m-d'),
                                                'is_active' => $job->is_active,
                                            ]); open = true"
                                            class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                                        >
                                            Edit
                                        </button>
                                        <form method="POST" action="{{ route('careers.toggle-active', $job) }}">
                                            @csrf
                                            <button type="submit" class="rounded-[8px] border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700">
                                                {{ $job->is_active ? 'Close' : 'Reopen' }}
                                            </button>
                                        </form>
                                        <form method="POST" action="{{ route('careers.destroy', $job) }}" onsubmit="return confirm('Remove &quot;{{ $job->title }}&quot;?');">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="rounded-[8px] border border-red-300 px-3 py-1.5 text-xs font-semibold text-red-700 transition-all duration-200 hover:-translate-y-0.5 hover:bg-red-50 dark:border-red-800 dark:text-red-400 dark:hover:bg-red-900/20">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No job postings yet. Click "Post a Job" to add one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($jobs->hasPages())
                <div class="border-t border-gray-100 p-4 dark:border-gray-700">
                    {{ $jobs->links() }}
                </div>
            @endif
        </div>

        {{-- Add/Edit Job modal --}}
        <div x-show="open" style="display: none;" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4">
            <div @click.outside="open = false" class="max-h-[90vh] w-full max-w-2xl overflow-y-auto rounded-[8px] bg-white p-6 dark:bg-gray-800">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white" x-text="editing ? 'Edit Job Posting' : 'Post a Job'"></h3>
                <form
                    method="POST"
                    :action="editing ? '{{ route('careers.update', ['job' => '__ID__']) }}'.replace('__ID__', editing.uuid) : '{{ route('careers.store') }}'"
                    class="mt-4 space-y-2"
                >
                    @csrf
                    <template x-if="editing"><input type="hidden" name="_method" value="PUT"></template>

                    <x-text-field name="title" label="Job Title" icon="M9 12.5l2 2 4-4.2 M7 4.5h10a1 1 0 011 1V19a1 1 0 01-1 1H7a1 1 0 01-1-1V5.5a1 1 0 011-1z" x-model="editing ? editing.title : ''" required />

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-text-field name="department" label="Department" icon="M12 4.5L3.5 9 12 13.5 20.5 9 12 4.5z" x-model="editing ? editing.department : ''" helper="Optional." />
                        <x-select-field name="employment_type" label="Employment Type" required :options="$typeOptions" model="editing ? editing.employment_type : 'full_time'" />
                        <x-text-field name="location" label="Location" icon="M12 21s7-6.1 7-11a7 7 0 10-14 0c0 4.9 7 11 7 11z" x-model="editing ? editing.location : ''" helper="Optional." />
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-text-field name="posted_at" label="Posted Date" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.posted_at : ''" />
                        <x-text-field name="closes_at" label="Closing Date" type="date" icon="M4.5 5.5h15a1 1 0 011 1V19a1 1 0 01-1 1h-15a1 1 0 01-1-1V6.5a1 1 0 011-1z" x-model="editing ? editing.closes_at : ''" helper="Optional." />
                    </div>

                    <x-textarea-field name="description" label="Job Description" icon="M4 5.5h16a1 1 0 011 1V16a1 1 0 01-1 1H8l-4 3.5V17a1 1 0 01-1-1V6.5a1 1 0 011-1z" rows="5" x-model="editing ? editing.description : ''" required />

                    <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" name="is_active" value="1" x-bind:checked="editing ? editing.is_active : true" class="w-4 text-blue-600">
                        Open (visible on your public website)
                    </label>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="open = false" class="rounded-[8px] border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</button>
                        <button type="submit" class="rounded-[8px] bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Save Job Posting</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-dashboard-layout>
